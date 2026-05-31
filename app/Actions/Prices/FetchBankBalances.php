<?php

declare(strict_types=1);

namespace App\Actions\Prices;

use App\Actions\Action;
use App\Http\Clients\EnableBankingClient;
use App\Models\Asset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FetchBankBalances extends Action
{
    public function __construct(
        private readonly EnableBankingClient $enableBanking,
    ) {}

    public function run(): PriceRefreshResult
    {
        $assets = Asset::whereNotNull('bank_account_uid')->get();

        $updated = [];
        $failed = [];

        foreach ($assets as $asset) {
            /** @var string $accountUid */
            $accountUid = $asset->bank_account_uid;

            $balance = $this->enableBanking->accountBalance($accountUid);

            if ($balance === null) {
                Log::warning('Bank balance unavailable', ['asset' => $asset->id]);
                $failed[] = $asset->name;

                continue;
            }

            // Overwrite the stored value with the live balance; a failed fetch
            // above leaves the previous value untouched.
            $asset->value = $balance['amount'];
            $asset->bank_synced_at = Carbon::now();
            $asset->save();
            $updated[] = $asset->name;
        }

        return new PriceRefreshResult($updated, $failed);
    }
}
