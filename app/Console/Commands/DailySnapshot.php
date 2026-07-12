<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Notifications\PushNotification;
use App\Actions\Prices\FetchAllPrices;
use App\Actions\Snapshots\StoreSnapshot;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DailySnapshot extends Command
{
    protected $signature = 'snapshots:daily';

    protected $description = 'Refresh every source and, only if all are fresh, take today\'s snapshot';

    public function handle(FetchAllPrices $fetchAllPrices, StoreSnapshot $storeSnapshot, PushNotification $notify): int
    {
        // Refresh everything first (prices, bank, Scalable — a daily run also
        // keeps the Scalable session alive via its rolling refresh token). The
        // result lists which sources could not be refreshed.
        $result = $fetchAllPrices->run();

        // Only snapshot when every source is fresh: a snapshot built on a stale
        // bank balance or a logged-out Scalable would record a guess, not a fact.
        // Skip and notify so the user knows to re-authenticate.
        if ($result->hasFailures()) {
            $failed = implode(', ', $result->failed);
            Log::warning('Daily snapshot skipped: stale sources', ['failed' => $result->failed]);

            $notify->run(
                type: Notification::TYPE_SNAPSHOT_SKIPPED,
                level: Notification::LEVEL_WARNING,
                title: 'Snapshot giornaliero saltato',
                body: 'Alcune fonti non erano aggiornate ('.$failed.'): lo snapshot userebbe dati vecchi. Ri-autentica la fonte e riprova.',
                actionUrl: '/settings',
                // One standing warning per source-set until read, so a multi-day
                // outage doesn't pile up identical notifications.
                dedupeKey: 'snapshot_skipped:'.$failed,
            );

            $this->warn('Snapshot skipped: stale sources ('.$failed.').');

            return Command::SUCCESS;
        }

        // firstOrNew(['date']) inside StoreSnapshot makes a same-day re-run
        // update in place rather than duplicate.
        $storeSnapshot->run(Carbon::now()->toDateString());
        $this->info('Daily snapshot saved.');

        return Command::SUCCESS;
    }
}
