<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Backup\CreateBackup;
use App\Actions\Notifications\PushNotification;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BackupHealthCheck extends Command
{
    public const STALE_KEY = 'backup_stale';

    protected $signature = 'backup:health';

    protected $description = 'Warn when the newest off-site backup is too old';

    public function handle(CreateBackup $createBackup, PushNotification $notify): int
    {
        if (! $createBackup->hasRemote()) {
            $this->line('No remote backup disk configured; nothing to check.');

            return Command::SUCCESS;
        }

        /** @var int $maxAgeDays */
        $maxAgeDays = config('backup.max_age_days');

        $latest = $createBackup->latestRemoteAt();

        // A failed run notifies on its own. This catches the quieter case: runs
        // that never happen at all (scheduler down, container not restarted),
        // where nothing throws and nothing is logged.
        if ($latest === null) {
            $this->warnStale($notify, 'Nessun backup trovato nello storage remoto.');
            $this->warn('No off-site backup found.');

            return Command::SUCCESS;
        }

        $ageDays = $latest->diffInDays(Carbon::now());

        if ($ageDays > $maxAgeDays) {
            $this->warnStale(
                $notify,
                'Il backup più recente risale a '.$latest->format('d/m/Y H:i').' ('.$ageDays.' giorni). Controlla che lo scheduler sia attivo.',
            );
            $this->warn("Newest off-site backup is {$ageDays} days old.");

            return Command::SUCCESS;
        }

        $notify->resolve(self::STALE_KEY);
        $this->info('Off-site backup is current ('.$latest->format('Y-m-d H:i').').');

        return Command::SUCCESS;
    }

    private function warnStale(PushNotification $notify, string $body): void
    {
        $notify->run(
            type: Notification::TYPE_BACKUP_STALE,
            level: Notification::LEVEL_WARNING,
            title: 'Backup non aggiornato',
            body: $body,
            actionUrl: '/settings',
            dedupeKey: self::STALE_KEY,
        );
    }
}
