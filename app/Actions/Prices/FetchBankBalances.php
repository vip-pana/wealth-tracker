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
            ->with('connection')
            ->whereNotNull('linked_name')
            ->whereNotNull('linked_category_id')
            ->whereHas('connection', fn ($q) => $q
                ->where('status', BankConnection::STATUS_ACTIVE)
                ->where(fn ($q2) => $q2->whereNull('valid_until')->orWhere('valid_until', '>', Carbon::now()))
            )
            ->get();

        $updated = [];
        $failed = [];

        foreach ($accounts as $account) {
            $label = $account->linked_name ?? $account->iban ?? (string) $account->id;

            $balance = $this->enableBanking->accountBalance($account->uid);

            // The bank rejected the session: consent was revoked or lapsed early.
            // Mark the connection expired so the UI surfaces "Riconnetti".
            if ($balance === 'unauthorized') {
                $account->connection->update(['status' => BankConnection::STATUS_EXPIRED]);
                $account->recordSyncFailure('Consenso non più valido. Riconnetti il conto.');
                $failed[] = $label;

                continue;
            }

            if ($balance === null) {
                Log::warning('Bank balance unavailable', ['bank_account' => $account->id]);
                $account->recordSyncFailure('Saldo non disponibile. Riprova più tardi.');
                $failed[] = $label;

                continue;
            }

            // Resolve (creating if needed) the linked asset's row for the current
            // month, so the balance follows the asset across monthly rows.
            $asset = $account->currentMonthAsset();
            if ($asset === null) {
                continue;
            }

            // Overwrite the stored value with the live balance; a failed fetch
            // above leaves the previous value untouched.
            $asset->value = $balance['amount'];
            $asset->bank_synced_at = Carbon::now();
            $asset->save();
            $account->recordSyncSuccess();
            $updated[] = $label;
        }

        return new PriceRefreshResult($updated, $failed);
    }
}
