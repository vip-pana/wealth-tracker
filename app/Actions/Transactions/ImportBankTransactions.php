<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Actions\Action;
use App\Actions\Notifications\PushNotification;
use App\Actions\Prices\FetchBankBalances;
use App\Http\Clients\EnableBankingClient;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Notification;
use Illuminate\Support\Carbon;

class ImportBankTransactions extends Action
{
    public function __construct(
        private readonly EnableBankingClient $enableBanking,
        private readonly PushNotification $notify,
    ) {}

    /**
     * Import each active account's transactions into bank_transactions.
     * Idempotent: a transaction is keyed by its Enable Banking id (external_id),
     * so the daily overlapping re-fetch updates in place instead of duplicating,
     * and the DB accumulates history past the ~90-day window the API returns.
     *
     * Transactions bind to the stable bank_account_id, not the rotating EB uid.
     * A rejected session (unauthorized) expires the connection and notifies,
     * mirroring FetchBankBalances; that account is skipped but the others run.
     *
     * @return array{imported: int, accounts: int}
     */
    public function run(): array
    {
        $accounts = BankAccount::query()
            ->with('connection')
            ->whereHas('connection', fn ($q) => $q
                ->where('status', BankConnection::STATUS_ACTIVE)
                ->where(fn ($q2) => $q2->whereNull('valid_until')->orWhere('valid_until', '>', Carbon::now()))
            )
            ->get();

        $imported = 0;
        $accountsSynced = 0;

        foreach ($accounts as $account) {
            $result = $this->importAccount($account);

            if ($result === null) {
                continue;
            }

            $imported += $result;
            $accountsSynced++;
        }

        return [
            'imported' => $imported,
            'accounts' => $accountsSynced,
        ];
    }

    /**
     * Import one account, paginating to exhaustion. Returns the number of
     * transactions written, or null if the account could not be synced
     * (unauthorized or a fetch failure) — the caller skips it.
     */
    private function importAccount(BankAccount $account): ?int
    {
        $imported = 0;
        $continuationKey = null;

        do {
            $page = $this->enableBanking->transactions($account->uid, $continuationKey);

            if ($page === 'unauthorized') {
                $this->markUnauthorized($account);

                return null;
            }

            // The bank's daily access quota is exhausted (429). Not an error to
            // act on: the next scheduled run tomorrow picks it up, and the
            // idempotent import means no data is lost. Record it plainly so the
            // UI doesn't read it as a broken connection.
            if ($page === 'rate_limited') {
                $account->recordSyncFailure('Limite giornaliero della banca raggiunto. Riprova domani.');

                return $continuationKey === null ? null : $imported;
            }

            // A transient failure (network, 5xx): on the first page nothing was
            // written, so report the account as unsynced; mid-pagination keep
            // what we already imported and stop this account.
            if ($page === null) {
                $account->recordSyncFailure('Transazioni non disponibili. Riprova più tardi.');

                return $continuationKey === null ? null : $imported;
            }

            foreach ($page['items'] as $item) {
                $transaction = BankTransaction::withTrashed()->firstOrNew(
                    ['external_id' => $item['external_id']],
                );

                $transaction->fill([
                    'bank_account_id' => $account->id,
                    'amount' => $item['amount'],
                    'currency' => $item['currency'],
                    'booking_date' => $item['booking_date'],
                    'value_date' => $item['value_date'],
                    'description' => $item['description'],
                    'counterparty' => $item['counterparty'],
                    'merchant_category_code' => $item['merchant_category_code'],
                    'raw' => $item['raw'],
                ]);
                // A user-deleted row is restored in place rather than left
                // soft-deleted beside a fresh duplicate.
                $transaction->deleted_at = null;
                $transaction->save();

                $imported++;
            }

            $continuationKey = $page['next_key'];
        } while ($continuationKey !== null);

        $account->recordSyncSuccess();

        return $imported;
    }

    private function markUnauthorized(BankAccount $account): void
    {
        $account->connection->update(['status' => BankConnection::STATUS_EXPIRED]);
        $account->recordSyncFailure('Consenso non più valido. Riconnetti il conto.');
        $this->notify->run(
            type: Notification::TYPE_BANK_CONSENT_EXPIRED,
            level: Notification::LEVEL_WARNING,
            title: 'Consenso bancario scaduto',
            body: $account->connection->aspsp_name.': riconnetti il conto dalle Impostazioni.',
            actionUrl: '/settings',
            dedupeKey: FetchBankBalances::consentExpiredKey($account->connection->id),
        );
    }
}
