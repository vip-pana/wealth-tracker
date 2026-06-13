<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Actions\Action;
use App\Models\Asset;

class UnlinkAssetTransactions extends Action
{
    /**
     * Detach an asset from its imported transactions, mirroring how
     * disconnecting a bank account leaves the asset behind: the transactions
     * are removed so the asset is no longer transaction-managed and its
     * quantity becomes editable by hand again, but the last computed quantity
     * stays on the row — the value isn't lost, only the link.
     */
    public function run(Asset $asset): void
    {
        $asset->transactions()->delete();
    }
}
