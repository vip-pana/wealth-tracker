<?php

declare(strict_types=1);

namespace App\Actions\Prices;

use App\Actions\Action;
use App\Http\Clients\ScalableCliClient;
use App\Models\Asset;
use App\Models\ScalableConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class FetchScalableBalance extends Action
{
    public function __construct(
        private readonly ScalableCliClient $cli,
    ) {}

    public function run(): PriceRefreshResult
    {
        $cashCategoryId = Config::integer('services.scalable.cash_category_id', 0);

        // Unconfigured: nothing to sync. (An asset carrying an ISIN is what opts
        // a holding in; cash is opted in by its category id.)
        if (! Config::boolean('services.scalable.cli.enabled', false)) {
            return new PriceRefreshResult;
        }

        // No live CLI session: record the failure and leave stored values as is.
        if (! $this->cli->isLoggedIn()) {
            ScalableConnection::current()->recordSyncFailure('Sincronizzazione non riuscita. Sessione Scalable scaduta: riconnetti.');

            return new PriceRefreshResult([], ['Scalable']);
        }

        $updated = [];
        $failed = [];

        $positions = $this->cli->positions();

        // Fetch failed (session expired mid-run): leave stored values as is.
        if ($positions === null) {
            $failed[] = 'Scalable';
        } else {
            foreach ($positions as $position) {
                $asset = $this->resolveByIsin($position['isin']);

                // No asset carries this ISIN: the holding isn't tracked here.
                if ($asset === null) {
                    continue;
                }

                $asset->value = $position['value'];
                $asset->synced_at = Carbon::now();
                $asset->sync_source = Asset::SYNC_SOURCE_BROKER;
                $asset->save();
                $updated[] = $asset->name;
            }
        }

        if ($cashCategoryId !== 0) {
            $cash = $this->cli->cashBalance();
            $cashName = Config::string('services.scalable.cash_asset_name', 'Scalable Liquidità');

            if ($cash === null) {
                $failed[] = $cashName;
            } else {
                $this->writeCash($cashName, $cashCategoryId, $cash);
                $updated[] = $cashName;
            }
        }

        // Persist the connection health so a failed sync stays visible in
        // Settings after the one-time toast is gone.
        $connection = ScalableConnection::current();
        if ($failed !== []) {
            $connection->recordSyncFailure('Sincronizzazione non riuscita. Sessione Scalable scaduta: riconnetti.');
        } else {
            $connection->recordSyncSuccess();
        }

        return new PriceRefreshResult($updated, $failed);
    }

    /**
     * The current-month row of the asset carrying this ISIN, or null if no asset
     * does. The ISIN→(name, category) identity is read from the most recent row
     * carrying it, then the current-month row is resolved (creating it if
     * needed) so the value follows the asset across months. A deleted
     * current-month row is restored in place rather than duplicated.
     */
    private function resolveByIsin(string $isin): ?Asset
    {
        $latest = Asset::withTrashed()
            ->where('isin', $isin)
            ->orderByDesc('date')
            ->first();

        if ($latest === null) {
            return null;
        }

        $asset = Asset::withTrashed()->firstOrNew([
            'name' => $latest->name,
            'category_id' => $latest->category_id,
            'date' => Carbon::now()->format('Y-m-01'),
        ]);

        $asset->isin = $isin;
        $asset->deleted_at = null;

        return $asset;
    }

    private function writeCash(string $name, int $categoryId, float $value): void
    {
        $asset = Asset::withTrashed()->firstOrNew([
            'name' => $name,
            'category_id' => $categoryId,
            'date' => Carbon::now()->format('Y-m-01'),
        ]);

        $asset->deleted_at = null;
        $asset->value = $value;
        $asset->synced_at = Carbon::now();
        $asset->sync_source = Asset::SYNC_SOURCE_BROKER;
        $asset->save();
    }
}
