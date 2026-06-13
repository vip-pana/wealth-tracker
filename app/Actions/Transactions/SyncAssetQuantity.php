<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Actions\Action;
use App\Models\Asset;

class SyncAssetQuantity extends Action
{
    public function __construct(
        private readonly ComputePosition $computePosition,
    ) {}

    /**
     * Recompute an asset's quantity from its transaction history and persist
     * it, so the existing valuation path (quantity × live price) reflects the
     * real shares held. Call this whenever an asset's transactions change
     * (create / update / delete).
     *
     * Leaves quantity untouched if the asset has no transactions — such an
     * asset is still manually managed and must not be silently zeroed.
     */
    public function run(Asset $asset): void
    {
        $transactions = $asset->transactions()->get();

        if ($transactions->isEmpty()) {
            return;
        }

        $position = $this->computePosition->run($transactions);

        $asset->quantity = $position['shares'];
        $asset->save();
    }
}
