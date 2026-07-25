<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Actions\Action;
use App\Http\Clients\ScalableCliClient;
use App\Models\Asset;
use App\Models\Transaction;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ImportScalableTransactions extends Action
{
    public function __construct(
        private readonly ScalableCliClient $cli,
        private readonly SyncAssetQuantity $syncAssetQuantity,
    ) {}

    /**
     * Import the full Scalable transaction history into our transactions table,
     * matching each to a tracked asset by ISIN. Idempotent: a transaction is
     * keyed by its Scalable id (external_id), so re-running updates in place
     * instead of duplicating. After import, each touched asset's quantity is
     * re-synced from its transactions.
     *
     * Returns a summary: how many transactions were imported/updated, and the
     * names of ISINs seen that no tracked asset carries (skipped).
     *
     * @return array{imported: int, skipped_isins: list<string>}|null null if the CLI is disabled or has no session
     */
    public function run(): ?array
    {
        if (! Config::boolean('services.scalable.cli.enabled', false)) {
            return null;
        }

        if (! $this->cli->isLoggedIn()) {
            return null;
        }

        $assetsByIsin = $this->trackedAssetsByIsin();

        $imported = 0;
        $skippedIsins = [];
        $touchedAssetIds = [];
        $cursor = null;

        do {
            $page = $this->cli->transactions($cursor);

            // A failure mid-pagination (e.g. session lapsed): stop, keep what we
            // already wrote, sync the assets touched so far.
            if ($page === null) {
                break;
            }

            foreach ($page['items'] as $item) {
                $asset = $assetsByIsin[$item['isin']] ?? null;

                if ($asset === null) {
                    $skippedIsins[$item['isin']] = $item['isin'];

                    continue;
                }

                Transaction::withTrashed()->updateOrCreate(
                    ['external_id' => $item['external_id']],
                    [
                        'asset_id' => $asset->id,
                        'type' => $item['type'],
                        'source' => $item['source'],
                        'shares' => $item['shares'],
                        'price_per_share' => $item['price_per_share'],
                        'date' => $item['date'],
                        'deleted_at' => null,
                    ],
                );

                $imported++;
                $touchedAssetIds[$asset->id] = $asset;
            }

            $cursor = $page['next_cursor'];
        } while ($cursor !== null);

        DB::transaction(function () use ($touchedAssetIds): void {
            foreach ($touchedAssetIds as $asset) {
                $this->syncAssetQuantity->run($asset);
            }
        });

        return [
            'imported' => $imported,
            'skipped_isins' => array_values($skippedIsins),
        ];
    }

    /**
     * The most recent asset row per ISIN — the identity a transaction binds to.
     * Mirrors how the balance sync resolves a holding by its ISIN.
     *
     * @return array<string, Asset>
     */
    private function trackedAssetsByIsin(): array
    {
        $byIsin = [];

        $assets = Asset::withTrashed()
            ->whereNotNull('isin')
            ->orderByDesc('date')
            ->get();

        foreach ($assets as $asset) {
            $isin = $asset->isin;

            // Keep only the most recent row per ISIN (first seen wins, given the
            // descending date order).
            if ($isin !== null && ! isset($byIsin[$isin])) {
                $byIsin[$isin] = $asset;
            }
        }

        return $byIsin;
    }
}
