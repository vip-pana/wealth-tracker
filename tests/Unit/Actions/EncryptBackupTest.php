<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Backup\EncryptBackup;
use RuntimeException;
use Tests\TestCase;

class EncryptBackupTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $dir = sys_get_temp_dir().'/backup-enc-'.bin2hex(random_bytes(6));
        mkdir($dir);
        $this->dir = $dir;

        config(['backup.encryption_key' => base64_encode(random_bytes(32))]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);

        parent::tearDown();
    }

    public function test_round_trips_a_file(): void
    {
        $plaintext = 'SQLite format 3'."\0".random_bytes(2048);

        $this->assertSame($plaintext, $this->roundTrip($plaintext));
    }

    /**
     * The cipher runs in 1 MiB chunks with the running IV carried across them,
     * so a payload spanning several chunks is the case that would break if the
     * chaining were wrong.
     */
    public function test_round_trips_a_file_larger_than_one_chunk(): void
    {
        $plaintext = random_bytes(2_500_000);

        $this->assertSame($plaintext, $this->roundTrip($plaintext));
    }

    /**
     * Block-aligned input is the boundary case for PKCS#7: the final block must
     * still be padded, or decryption silently truncates.
     */
    public function test_round_trips_block_aligned_input(): void
    {
        $plaintext = str_repeat('A', 1024);

        $this->assertSame($plaintext, $this->roundTrip($plaintext));
    }

    public function test_round_trips_an_empty_file(): void
    {
        $this->assertSame('', $this->roundTrip(''));
    }

    public function test_ciphertext_does_not_contain_the_plaintext(): void
    {
        $encrypt = new EncryptBackup;
        $source = $this->write('db.sqlite', 'SUPER-SECRET-BALANCE-12345');
        $archive = $this->dir.'/db.enc';

        $encrypt->encrypt($source, $archive);

        $this->assertStringNotContainsString(
            'SUPER-SECRET-BALANCE-12345',
            (string) file_get_contents($archive),
        );
    }

    public function test_rejects_a_tampered_archive(): void
    {
        $encrypt = new EncryptBackup;
        $source = $this->write('db.sqlite', random_bytes(4096));
        $archive = $this->dir.'/db.enc';

        $encrypt->encrypt($source, $archive);

        $bytes = (string) file_get_contents($archive);
        $bytes[100] = $bytes[100] === 'x' ? 'y' : 'x';
        file_put_contents($archive, $bytes);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/integrity check/');

        $encrypt->decrypt($archive, $this->dir.'/out.sqlite');
    }

    public function test_rejects_a_truncated_archive(): void
    {
        $encrypt = new EncryptBackup;
        $source = $this->write('db.sqlite', random_bytes(4096));
        $archive = $this->dir.'/db.enc';

        $encrypt->encrypt($source, $archive);

        $bytes = (string) file_get_contents($archive);
        file_put_contents($archive, substr($bytes, 0, -64));

        $this->expectException(RuntimeException::class);

        $encrypt->decrypt($archive, $this->dir.'/out.sqlite');
    }

    public function test_rejects_the_wrong_key(): void
    {
        $encrypt = new EncryptBackup;
        $source = $this->write('db.sqlite', random_bytes(4096));
        $archive = $this->dir.'/db.enc';

        $encrypt->encrypt($source, $archive);

        config(['backup.encryption_key' => base64_encode(random_bytes(32))]);

        $this->expectException(RuntimeException::class);

        (new EncryptBackup)->decrypt($archive, $this->dir.'/out.sqlite');
    }

    /**
     * A tampered archive must be rejected before any plaintext is written —
     * a half-restored database is worse than a failed restore.
     */
    public function test_writes_no_output_when_verification_fails(): void
    {
        $encrypt = new EncryptBackup;
        $source = $this->write('db.sqlite', random_bytes(4096));
        $archive = $this->dir.'/db.enc';
        $target = $this->dir.'/out.sqlite';

        $encrypt->encrypt($source, $archive);

        $bytes = (string) file_get_contents($archive);
        $bytes[200] = $bytes[200] === 'x' ? 'y' : 'x';
        file_put_contents($archive, $bytes);

        try {
            $encrypt->decrypt($archive, $target);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertFileDoesNotExist($target);
    }

    public function test_fails_when_no_key_is_configured(): void
    {
        config(['backup.encryption_key' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/BACKUP_ENCRYPTION_KEY/');

        (new EncryptBackup)->encrypt(
            $this->write('db.sqlite', 'x'),
            $this->dir.'/db.enc',
        );
    }

    public function test_fails_on_a_malformed_key(): void
    {
        config(['backup.encryption_key' => 'not-base64-32-bytes']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/32 base64-encoded bytes/');

        (new EncryptBackup)->encrypt(
            $this->write('db.sqlite', 'x'),
            $this->dir.'/db.enc',
        );
    }

    private function roundTrip(string $plaintext): string
    {
        $encrypt = new EncryptBackup;
        $source = $this->write('db.sqlite', $plaintext);
        $archive = $this->dir.'/db.enc';
        $target = $this->dir.'/restored.sqlite';

        $encrypt->encrypt($source, $archive);
        $encrypt->decrypt($archive, $target);

        return (string) file_get_contents($target);
    }

    private function write(string $name, string $contents): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, $contents);

        return $path;
    }
}
