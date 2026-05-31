<?php

declare(strict_types=1);

namespace App\Actions\Prices;

use App\Actions\Action;
use App\Http\Clients\EnableBankingClient;
use App\Models\BankAccount;
use App\Models\BankConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FetchBankBalances extends Action
{
    public function __construct(
        private readonly EnableBankingClient $enableBanking,
    ) {}

    public function run(): PriceRefreshResult
    {
        // Linked accounts whose connection is still active and not expired.
        $accounts = BankAccount::query()
            ->whereNotNull('asset_id')
            ->whereHas('connection', fn ($q) => $q
                ->where('status', BankConnection::STATUS_ACTIVE)
                ->where(fn ($q2) => $q2->whereNull('valid_until')->orWhere('valid_until', '>', Carbon::now()))
            )
            ->with('asset')
            ->get();

        $updated = [];
        $failed = [];

        foreach ($accounts as $account) {
            $asset = $account->asset;
            if ($asset === null) {
                continue;
            }

            $balance = $this->enableBanking->accountBalance($account->uid);

            if ($balance === null) {
                Log::warning('Bank balance unavailable', ['bank_account' => $account->id]);
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
