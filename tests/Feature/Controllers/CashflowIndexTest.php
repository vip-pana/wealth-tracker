<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CashflowIndexTest extends TestCase
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

    private function transaction(BankAccount $account, string $date, float $amount, string $flow, string $note = ''): BankTransaction
    {
        $n = ++$this->seq;

        return BankTransaction::create([
            'bank_account_id' => $account->id, 'external_id' => 'tx-'.$n,
            'amount' => $amount, 'currency' => 'EUR', 'booking_date' => $date,
            'flow_type' => $flow, 'raw' => ['remittance_information' => [$note]],
        ]);
    }

    public function test_defaults_to_the_current_month(): void
    {
        $this->get('/cashflow')->assertInertia(fn (Assert $page) => $page
            ->where('month', now()->format('Y-m-01'))
        );
    }

    public function test_returns_only_the_transactions_of_the_requested_month(): void
    {
        $account = $this->account();
        $june = $this->transaction($account, '2026-06-15', -20.0, BankTransaction::FLOW_EXPENSE);
        $july = $this->transaction($account, '2026-07-31', -30.0, BankTransaction::FLOW_EXPENSE);

        $this->get('/cashflow?month=2026-07-01')->assertInertia(fn (Assert $page) => $page
            ->where('month', '2026-07-01')
            ->has('transactions', 1)
            ->where('transactions.0.id', $july->id)
        );

        $this->get('/cashflow?month=2026-06-01')->assertInertia(fn (Assert $page) => $page
            ->has('transactions', 1)
            ->where('transactions.0.id', $june->id)
        );
    }

    public function test_lists_available_months_newest_first(): void
    {
        $account = $this->account();
        $this->transaction($account, '2026-06-15', -20.0, BankTransaction::FLOW_EXPENSE);
        $this->transaction($account, '2026-07-02', -30.0, BankTransaction::FLOW_EXPENSE);
        $this->transaction($account, '2026-07-20', -40.0, BankTransaction::FLOW_EXPENSE);

        $this->get('/cashflow')->assertInertia(fn (Assert $page) => $page
            ->where('availableMonths', ['2026-07-01', '2026-06-01'])
        );
    }

    public function test_monthly_averages_cover_the_whole_history_not_the_shown_month(): void
    {
        $account = $this->account();
        // Two months of history: 100 + 300 of expense over 2 months = 200/month,
        // and a single salary credit, so the salary average divides by 1.
        $this->transaction($account, '2026-06-10', -100.0, BankTransaction::FLOW_EXPENSE);
        $this->transaction($account, '2026-07-10', -300.0, BankTransaction::FLOW_EXPENSE);
        $this->transaction($account, '2026-07-27', 2000.0, BankTransaction::FLOW_INCOME, 'ACCREDITO STIPENDIO');

        $this->get('/cashflow?month=2026-06-01')->assertInertia(fn (Assert $page) => $page
            ->where('emergencyFund.monthlyExpense', 200)
            ->where('monthlySalary', 2000)
        );
    }

    public function test_rejects_a_malformed_month(): void
    {
        $this->get('/cashflow?month=2026-07')->assertSessionHasErrors('month');
    }

    public function test_clamps_a_future_month_to_the_current_one(): void
    {
        $future = now()->addMonths(3)->format('Y-m-01');

        $this->get('/cashflow?month='.$future)->assertInertia(fn (Assert $page) => $page
            ->where('month', now()->format('Y-m-01'))
        );
    }

    public function test_position_returns_is_null_without_transaction_managed_assets(): void
    {
        Asset::factory()->create(['date' => '2026-06-01']);

        $this->get('/cashflow?month=2026-06-01')->assertInertia(fn (Assert $page) => $page
            ->where('positionReturns', null)
        );
    }

    public function test_position_returns_spans_the_whole_history_not_the_shown_month(): void
    {
        // Positions are built from transactions, which carry no month scoping:
        // asking for a month with no transactions still returns the position.
        $asset = Asset::factory()->create(['name' => 'ACWI', 'ticker' => 'ACWI', 'isin' => 'IE00B6R52259', 'date' => '2026-05-01']);
        Transaction::factory()->for($asset)->create([
            'type' => Transaction::TYPE_BUY, 'shares' => 10, 'price_per_share' => 100, 'date' => '2026-05-10',
        ]);
        AssetPrice::create(['ticker' => 'ACWI', 'price' => 120, 'currency' => 'EUR', 'fetched_at' => now()]);

        // The JSON round-trip drops the ".0", so compare loosely on value.
        $this->get('/cashflow?month=2026-08-01')->assertInertia(fn (Assert $page) => $page
            ->where('positionReturns.aggregate.cost_basis', 1000)
            ->where('positionReturns.aggregate.current_value', 1200)
        );
    }
}
