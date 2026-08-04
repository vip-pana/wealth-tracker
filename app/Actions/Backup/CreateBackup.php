<?php

declare(strict_types=1);

namespace App\Actions\Backup;

use App\Actions\Action;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CreateBackup extends Action
{
    public const ARCHIVE_SUFFIX = '.sqlite.enc';

    public function __construct(
        private readonly EncryptBackup $encrypt,
    ) {}

    /**
     * Snapshot the database and, when a remote disk is configured, ship an
     * encrypted copy off the machine. Returns the name of the artifact the user
     * should see — the remote object key when uploading, the local file
     * otherwise.
     */
    public function run(): string
    {
        /** @var string $backupDir */
        $backupDir = config('backup.directory');
        /** @var int $retentionDays */
        $retentionDays = config('backup.retention_days');

        if (! is_dir($backupDir)) {
            throw new RuntimeException("Backup directory not found: {$backupDir}");
        }
        if (! is_writable($backupDir)) {
            throw new RuntimeException("Backup directory not writable: {$backupDir}");
        }

        $timestamp = Carbon::now()->format('Ymd-His');
        $localPath = $backupDir.'/database-'.$timestamp.'.sqlite';

        // VACUUM INTO is atomic and safe while the app is writing: SQLite reads
        // a consistent view and writes a fresh, defragmented file.
        DB::statement("VACUUM INTO '".$localPath."'");

        $disk = $this->remoteDisk();

        if ($disk === null) {
            $this->cleanupOldBackups($backupDir, $retentionDays);

            return basename($localPath);
        }

        $encryptedPath = $localPath.'.enc';

        try {
            $this->encrypt->encrypt($localPath, $encryptedPath);
            $key = $this->upload($disk, $encryptedPath, $timestamp);
        } finally {
            // The plaintext snapshot and the staged archive are byproducts: the
            // durable copy is the uploaded object. Removing them keeps an
            // unencrypted database from lingering on disk.
            @unlink($encryptedPath);
            @unlink($localPath);
        }

        $this->pruneRemote($disk, $retentionDays);
        $this->cleanupOldBackups($backupDir, $retentionDays);

        return $key;
    }

    /**
     * The most recent off-site backup's timestamp, or null when nothing is
     * stored remotely. Used by the health check to spot a backup chain that
     * quietly stopped.
     */
    public function latestRemoteAt(): ?Carbon
    {
        $disk = $this->remoteDisk();

        if ($disk === null) {
            return null;
        }

        $latest = null;

        foreach ($disk->files($this->remotePrefix()) as $file) {
            $at = $this->timestampFromKey($file);

            if ($at !== null && ($latest === null || $at->greaterThan($latest))) {
                $latest = $at;
            }
        }

        return $latest;
    }

    public function hasRemote(): bool
    {
        return $this->remoteDisk() !== null;
    }

    private function upload(Filesystem $disk, string $encryptedPath, string $timestamp): string
    {
        $key = $this->remotePrefix().'/database-'.$timestamp.self::ARCHIVE_SUFFIX;

        $stream = fopen($encryptedPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to read staged backup: {$encryptedPath}");
        }

        try {
            // Streamed so the upload's memory footprint stays flat, and checked
            // explicitly: the s3 disk is configured with throw => false, so a
            // failed PUT returns false instead of raising.
            $written = $disk->writeStream($key, $stream);
        } catch (Throwable $e) {
            throw new RuntimeException('Backup upload failed: '.$e->getMessage(), previous: $e);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($written === false) {
            throw new RuntimeException("Backup upload failed: could not write {$key}.");
        }

        return $key;
    }

    private function pruneRemote(Filesystem $disk, int $retentionDays): void
    {
        $cutoff = Carbon::now()->subDays($retentionDays);

        foreach ($disk->files($this->remotePrefix()) as $file) {
            $at = $this->timestampFromKey($file);

            if ($at !== null && $at->lessThan($cutoff)) {
                $disk->delete($file);
            }
        }
    }

    /**
     * The retention window is read from the key's own timestamp rather than the
     * object's mtime: re-uploads and provider-side copies rewrite mtime, which
     * would keep pruning from ever catching up.
     */
    private function timestampFromKey(string $key): ?Carbon
    {
        if (preg_match('/database-(\d{8}-\d{6})/', $key, $matches) !== 1) {
            return null;
        }

        return Carbon::createFromFormat('Ymd-His', $matches[1]) ?: null;
    }

    private function remoteDisk(): ?Filesystem
    {
        /** @var string|null $name */
        $name = config('backup.disk');

        if ($name === null || $name === '') {
            return null;
        }

        return Storage::disk($name);
    }

    private function remotePrefix(): string
    {
        /** @var string $prefix */
        $prefix = config('backup.disk_path');

        return trim($prefix, '/');
    }

    private function cleanupOldBackups(string $dir, int $retentionDays): void
    {
        $cutoff = Carbon::now()->subDays($retentionDays)->timestamp;
        $files = glob($dir.'/database-*.sqlite') ?: [];
        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime !== false && $mtime < $cutoff) {
                @unlink($file);
            }
        }
    }
}
