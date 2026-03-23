<?php

declare(strict_types=1);

namespace App\Actions\Prices;

use App\Actions\Action;
use App\Http\Clients\BlockstreamClient;
use App\Models\Asset;

class FetchWalletBalances extends Action
{
    public function __construct(
        private readonly BlockstreamClient $blockstream,
    ) {}

    public function run(): void
    {
        $assets = Asset::whereNotNull('wallet_address')->get();

        foreach ($assets as $asset) {
            /** @var string $address */
            $address = $asset->wallet_address;

            $btc = $this->blockstream->getBalanceBtc($address);

            if ($btc === null) {
                continue;
            }

            $asset->quantity = $btc;
            $asset->save();
        }
    }
}
