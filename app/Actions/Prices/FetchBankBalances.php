<?php

declare(strict_types=1);

namespace App\Actions\Prices;

use App\Actions\Action;
use App\Http\Clients\GoCardlessClient;
use App\Models\Asset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FetchBankBalances extends Action
{
    public function __construct(
        private readonly GoCardlessClient $goCardless,
    ) {}

    public function run(): PriceRefreshResult
    {
        $assets = Asset::whereNotNull('gocardless_account_id')->get();

        $updated = [];
        $failed = [];

        foreach ($assets as $asset) {
            /** @var string $accountId */
            $accountId = $asset->gocardless_account_id;

            $balance = $this->goCardless->accountBalance($accountId);

            if ($balance === null) {
                Log::warning('GoCardless balance unavailable', ['asset' => $asset->id]);
                $failed[] = $asset->name;

                continue;
            }

            // Overwrite the stored value with the live balance; a failed fetch
            // above leaves the previous value untouched.
            $asset->value = $balance['amount'];
            $asset->gocardless_synced_at = Carbon::now();
            $asset->save();
            $updated[] = $asset->name;
        }

        return new PriceRefreshResult($updated, $failed);
    }
}
