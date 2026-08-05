<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Backup\CreateBackup;
use App\Actions\Notifications\PushNotification;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunBackup extends Command
{
    public const FAILED_KEY = 'backup_failed';

    protected $signature = 'backup:run';

    protected $description = 'Back up the database and ship an encrypted copy off-site';

    public function handle(CreateBackup $createBackup, PushNotification $notify): int
    {
        try {
            $artifact = $createBackup->run();
        } catch (Throwable $e) {
            Log::error('Scheduled backup failed', ['reason' => $e->getMessage()]);

            // A backup that stops running is silent by nature, so the failure
            // has to surface where the user already looks.
            $notify->run(
                type: Notification::TYPE_BACKUP_FAILED,
                level: Notification::LEVEL_WARNING,
                title: 'Backup non riuscito',
                body: 'Il backup automatico è fallito: '.$e->getMessage(),
                actionUrl: '/settings',
                dedupeKey: self::FAILED_KEY,
            );

            $this->error('Backup failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        $notify->resolve(self::FAILED_KEY);
        $notify->resolve(BackupHealthCheck::STALE_KEY);

        $this->info('Backup created: '.$artifact);

        return Command::SUCCESS;
    }
}
