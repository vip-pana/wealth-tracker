<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Transactions\ImportBankTransactions;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportBankTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->keyPath = tempnam(sys_get_temp_dir(), 'eb_test_key');
        file_put_contents($this->keyPath, $pem);

        config([
            'services.enable_banking.application_id' => 'app-uuid',
            'services.enable_banking.private_key_path' => $this->keyPath,
        ]);
    }

    protected function tearDown(): void
    {
        Cache::forget('enable_banking.jwt');
        @unlink($this->keyPath);
        parent::tearDown();
    }

    private function account(string $uid, string $status = BankConnection::STATUS_ACTIVE, ?Carbon $validUntil = null): BankAccount
    {
        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut',
            'aspsp_country' => 'IT',
            'state' => 'state-'.$uid,
            'session_id' => 'sess-1',
            'status' => $status,
            'valid_until' => $validUntil ?? Carbon::now()->addDays(30),
        ]);

        return $connection->accounts()->create(['uid' => $uid, 'iban' => 'IT'.$uid]);
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     * @return array<string, mixed>
     */
    private function page(array $transactions, ?string $continuationKey = null): array
    {
        return ['transactions' => $transactions, 'continuation_key' => $continuationKey];
    }

    /** @return array<string, mixed> */
    private function debit(string $id, string $amount = '12.50', string $date = '2026-07-01'): array
    {
        return [
            'entry_reference' => $id,
            'transaction_amount' => ['amount' => $amount, 'currency' => 'EUR'],
            'credit_debit_indicator' => 'DBIT',
            'booking_date' => $date,
            'value_date' => $date,
            'remittance_information' => 'Coffee',
            'creditor' => 'Bar Rossi',
            'merchant_category_code' => '5812',
        ];
    }

    /** @return array<string, mixed> */
    private function credit(string $id, string $amount = '1800.00', string $date = '2026-07-01'): array
    {
        return [
            'entry_reference' => $id,
            'transaction_amount' => ['amount' => $amount, 'currency' => 'EUR'],
            'credit_debit_indicator' => 'CRDT',
            'booking_date' => $date,
            'value_date' => $date,
            'remittance_information' => 'Salary',
            'debtor' => 'ACME SpA',
        ];
    }

    public function test_imports_transactions_for_an_active_account(): void
    {
        $account = $this->account('acc-1');
        Http::fake([
            'api.enablebanking.com/accounts/acc-1/transactions*' => Http::response(
                $this->page([$this->debit('tx-1'), $this->credit('tx-2')])
            ),
        ]);

        $result = app(ImportBankTransactions::class)->run();

        $this->assertSame(['imported' => 2, 'accounts' => 1], $result);
        $this->assertDatabaseHas('bank_transactions', [
            'external_id' => 'tx-1', 'bank_account_id' => $account->id,
            'amount' => -12.50, 'currency' => 'EUR', 'counterparty' => 'Bar Rossi',
            'merchant_category_code' => '5812',
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'external_id' => 'tx-2', 'amount' => 1800.00, 'counterparty' => 'ACME SpA',
        ]);
        $this->assertDatabaseHas('bank_accounts', ['uid' => 'acc-1', 'last_sync_status' => 'ok']);
    }

    public function test_debit_is_negative_and_credit_is_positive(): void
    {
        $this->account('acc-1');
        Http::fake([
            'api.enablebanking.com/accounts/acc-1/transactions*' => Http::response(
                $this->page([$this->debit('d', '30.00'), $this->credit('c', '30.00')])
            ),
        ]);

        app(ImportBankTransactions::class)->run();

        $this->assertSame(-30.0, BankTransaction::where('external_id', 'd')->value('amount'));
        $this->assertSame(30.0, BankTransaction::where('external_id', 'c')->value('amount'));
    }

    public function test_paginates_via_continuation_key(): void
    {
        $this->account('acc-1');
        Http::fakeSequence('api.enablebanking.com/accounts/acc-1/transactions*')
            ->push($this->page([$this->debit('tx-1')], 'next-key'))
            ->push($this->page([$this->debit('tx-2')]));

        $result = app(ImportBankTransactions::class)->run();

        $this->assertSame(2, $result['imported']);
        $this->assertSame(2, BankTransaction::count());
    }

    public function test_reimport_is_idempotent(): void
    {
        $this->account('acc-1');
        $fake = fn () => Http::fake([
            'api.enablebanking.com/accounts/acc-1/transactions*' => Http::response(
                $this->page([$this->debit('tx-1')])
            ),
        ]);

        $fake();
        app(ImportBankTransactions::class)->run();
        $fake();
        app(ImportBankTransactions::class)->run();

        $this->assertSame(1, BankTransaction::where('external_id', 'tx-1')->count());
    }

    public function test_expires_the_connection_when_the_bank_rejects_the_session(): void
    {
        $this->account('acc-1');
        Http::fake(['api.enablebanking.com/accounts/acc-1/transactions*' => Http::response('', 403)]);

        $result = app(ImportBankTransactions::class)->run();

        $this->assertSame(['imported' => 0, 'accounts' => 0], $result);
        $this->assertDatabaseHas('bank_connections', [
            'state' => 'state-acc-1', 'status' => BankConnection::STATUS_EXPIRED,
        ]);
        $this->assertDatabaseHas('bank_accounts', ['uid' => 'acc-1', 'last_sync_status' => 'failed']);
    }

    public function test_rate_limited_account_is_skipped_without_expiring_the_connection(): void
    {
        $this->account('acc-1');
        Http::fake(['api.enablebanking.com/accounts/acc-1/transactions*' => Http::response(['error' => 'ASPSP_RATE_LIMIT_EXCEEDED'], 429)]);

        $result = app(ImportBankTransactions::class)->run();

        $this->assertSame(['imported' => 0, 'accounts' => 0], $result);
        $this->assertSame(0, BankTransaction::count());
        // The connection stays active — a rate limit is not a revoked consent.
        $this->assertDatabaseHas('bank_connections', [
            'state' => 'state-acc-1', 'status' => BankConnection::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('bank_accounts', [
            'uid' => 'acc-1', 'last_sync_status' => 'failed',
            'last_sync_error' => 'Limite giornaliero della banca raggiunto. Riprova domani.',
        ]);
    }

    public function test_a_transient_failure_skips_the_account(): void
    {
        $this->account('acc-1');
        Http::fake(['api.enablebanking.com/accounts/acc-1/transactions*' => Http::response('', 500)]);

        $result = app(ImportBankTransactions::class)->run();

        $this->assertSame(['imported' => 0, 'accounts' => 0], $result);
        $this->assertSame(0, BankTransaction::count());
        $this->assertDatabaseHas('bank_accounts', ['uid' => 'acc-1', 'last_sync_status' => 'failed']);
    }

    public function test_skips_expired_connections(): void
    {
        $this->account('acc-1', BankConnection::STATUS_ACTIVE, Carbon::now()->subDay());
        Http::fake(['api.enablebanking.com/accounts/acc-1/transactions*' => Http::response($this->page([$this->debit('tx-1')]))]);

        $result = app(ImportBankTransactions::class)->run();

        $this->assertSame(['imported' => 0, 'accounts' => 0], $result);
        $this->assertSame(0, BankTransaction::count());
    }

    public function test_restores_a_trashed_transaction_instead_of_duplicating(): void
    {
        $account = $this->account('acc-1');
        BankTransaction::create([
            'bank_account_id' => $account->id, 'external_id' => 'tx-1',
            'amount' => -12.50, 'currency' => 'EUR', 'booking_date' => '2026-07-01',
        ])->delete();
        Http::fake(['api.enablebanking.com/accounts/acc-1/transactions*' => Http::response($this->page([$this->debit('tx-1')]))]);

        app(ImportBankTransactions::class)->run();

        $this->assertNotSoftDeleted('bank_transactions', ['external_id' => 'tx-1']);
        $this->assertSame(1, BankTransaction::where('external_id', 'tx-1')->count());
    }
}
