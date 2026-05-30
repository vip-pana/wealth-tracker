<?php

declare(strict_types=1);

namespace App\Actions\Backup;

use App\Actions\Action;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateBackup extends Action
{
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
        $finalPath = $backupDir.'/database-'.$timestamp.'.sqlite';

        DB::statement("VACUUM INTO '".$finalPath."'");

        $this->cleanupOldBackups($backupDir, $retentionDays);

        return $finalPath;
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
