<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Transactions\FetchTransactionMonths;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FetchTransactionMonthsTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function account(): BankAccount
    {
        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut', 'aspsp_country' => 'IT',
            'state' => 'state-1', 'session_id' => 'sess-1',
            'status' => BankConnection::STATUS_ACTIVE,
        ]);

        return $connection->accounts()->create(['uid' => 'acc-1', 'iban' => 'IT01']);
    }

    private function transaction(BankAccount $account, string $date): void
    {
        $n = ++$this->seq;

        BankTransaction::create([
            'bank_account_id' => $account->id, 'external_id' => 'tx-'.$n,
            'amount' => -10.0, 'currency' => 'EUR', 'booking_date' => $date,
            'flow_type' => BankTransaction::FLOW_EXPENSE,
        ]);
    }

    public function test_returns_distinct_months_newest_first(): void
    {
        $account = $this->account();
        $this->transaction($account, '2026-05-31');
        $this->transaction($account, '2026-07-01');
        $this->transaction($account, '2026-07-18');

        $this->assertSame(
            ['2026-07-01', '2026-05-01'],
            app(FetchTransactionMonths::class)->run(),
        );
    }

    public function test_returns_an_empty_list_without_transactions(): void
    {
        $this->assertSame([], app(FetchTransactionMonths::class)->run());
    }
}
