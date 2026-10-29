<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Backup\CreateBackup;
use App\Actions\Backup\EncryptBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        // SQLite refuses to VACUUM inside a transaction, and RefreshDatabase
        // opens one per test. Commit it here so VACUUM INTO — how the backup is
        // actually taken — can run. The migrated in-memory database is still
        // rebuilt between tests, so isolation holds.
        while (DB::transactionLevel() > 0) {
            DB::commit();
        }

        $dir = sys_get_temp_dir().'/backup-create-'.bin2hex(random_bytes(6));
        mkdir($dir);
        $this->dir = $dir;

        config([
            'backup.directory' => $dir,
            'backup.retention_days' => 30,
            'backup.disk' => 'backups',
            'backup.disk_path' => 'wealth-tracker',
            'backup.encryption_key' => base64_encode(random_bytes(32)),
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);

        parent::tearDown();
    }

    public function test_uploads_an_encrypted_backup_to_the_remote_disk(): void
    {
        $disk = Storage::fake('backups');

        $key = $this->action()->run();

        $disk->assertExists($key);
        $this->assertStringStartsWith('wealth-tracker/database-', $key);
        $this->assertStringEndsWith('.sqlite.enc', $key);
    }

    /**
     * The uploaded object must be the encrypted archive, not the raw database:
     * the SQLite header would betray a plaintext upload.
     */
    public function test_the_uploaded_object_is_not_plaintext(): void
    {
        $disk = Storage::fake('backups');

        $key = $this->action()->run();

        $this->assertStringNotContainsString(
            'SQLite format 3',
            (string) $disk->get($key),
        );
    }

    public function test_the_uploaded_backup_decrypts_into_a_usable_database(): void
    {
        $disk = Storage::fake('backups');

        $key = $this->action()->run();

        $archive = $this->dir.'/downloaded.enc';
        $restored = $this->dir.'/restored.sqlite';
        file_put_contents($archive, (string) $disk->get($key));

        (new EncryptBackup)->decrypt($archive, $restored);

        $this->assertStringStartsWith(
            'SQLite format 3',
            (string) file_get_contents($restored),
        );
    }

    /**
     * The plaintext snapshot is a staging artifact. Leaving it behind would keep
     * an unencrypted copy of the whole financial history on disk.
     */
    public function test_leaves_no_plaintext_behind_after_uploading(): void
    {
        Storage::fake('backups');

        $this->action()->run();

        $this->assertSame([], glob($this->dir.'/database-*.sqlite') ?: []);
        $this->assertSame([], glob($this->dir.'/*.enc') ?: []);
    }

    public function test_keeps_the_local_file_when_no_remote_disk_is_configured(): void
    {
        config(['backup.disk' => null]);

        $name = $this->action()->run();

        $this->assertFileExists($this->dir.'/'.$name);
        $this->assertStringStartsWith('SQLite format 3', (string) file_get_contents($this->dir.'/'.$name));
    }

    public function test_prunes_remote_backups_past_the_retention_window(): void
    {
        $disk = Storage::fake('backups');

        $old = 'wealth-tracker/database-'.Carbon::now()->subDays(45)->format('Ymd-His').'.sqlite.enc';
        $recent = 'wealth-tracker/database-'.Carbon::now()->subDays(5)->format('Ymd-His').'.sqlite.enc';
        $disk->put($old, 'old');
        $disk->put($recent, 'recent');

        $this->action()->run();

        $disk->assertMissing($old);
        $disk->assertExists($recent);
    }

    public function test_fails_loudly_when_the_upload_cannot_happen(): void
    {
        Storage::fake('backups');
        config(['backup.encryption_key' => null]);

        $this->expectException(\RuntimeException::class);

        $this->action()->run();
    }

    public function test_reports_the_newest_remote_backup(): void
    {
        $disk = Storage::fake('backups');

        $disk->put('wealth-tracker/database-20260101-120000.sqlite.enc', 'a');
        $disk->put('wealth-tracker/database-20260715-093000.sqlite.enc', 'b');

        $latest = $this->action()->latestRemoteAt();

        $this->assertNotNull($latest);
        $this->assertSame('2026-07-15 09:30:00', $latest->format('Y-m-d H:i:s'));
    }

    public function test_reports_no_remote_backup_when_the_bucket_is_empty(): void
    {
        Storage::fake('backups');

        $this->assertNull($this->action()->latestRemoteAt());
    }

    /**
     * With no remote disk — the default — the plaintext snapshot stays in the
     * backup directory for the whole retention window. That file is the entire
     * financial history, and at the default 0644 every other account on the
     * host, and every container mounting the same directory, could read it.
     */
    public function test_the_local_snapshot_is_readable_only_by_its_owner(): void
    {
        config(['backup.disk' => '']);

        $name = $this->action()->run();

        $this->assertSame('0600', $this->mode($this->dir.'/'.$name));
    }

    /**
     * The staged archive and the restored database are written by EncryptBackup
     * rather than by the action, so the guard has to cover anything it opens —
     * a restore's output is a plaintext database like any other.
     */
    public function test_everything_written_under_the_guard_is_owner_only(): void
    {
        $plain = $this->dir.'/plain.sqlite';
        $archive = $this->dir.'/plain.sqlite.enc';
        $restored = $this->dir.'/restored.sqlite';
        file_put_contents($plain, 'not really a database');

        CreateBackup::privately(function () use ($plain, $archive, $restored): void {
            $encrypt = new EncryptBackup;
            $encrypt->encrypt($plain, $archive);
            $encrypt->decrypt($archive, $restored);
        });

        $this->assertSame('0600', $this->mode($archive));
        $this->assertSame('0600', $this->mode($restored));
    }

    /** The umask must not leak into whatever the queue worker does next. */
    public function test_the_private_umask_is_restored_afterwards(): void
    {
        $before = umask();

        CreateBackup::privately(fn () => null);
        $this->assertSame($before, umask());

        try {
            CreateBackup::privately(fn () => throw new \RuntimeException('boom'));
        } catch (\RuntimeException) {
            // Expected — the point is the umask below, not the exception.
        }

        $this->assertSame($before, umask());
    }

    private function mode(string $path): string
    {
        clearstatcache(true, $path);

        return substr(sprintf('%o', fileperms($path)), -4);
    }

    private function action(): CreateBackup
    {
        return new CreateBackup(new EncryptBackup);
    }
}
