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

    private function transaction(BankAccount $account, string $date, float $amount, ?string $flow, string $note = ''): BankTransaction
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

    public function test_monthly_flow_returns_one_point_per_month_oldest_first(): void
    {
        $account = $this->account();
        $this->transaction($account, '2026-07-10', 2000.0, BankTransaction::FLOW_INCOME);
        $this->transaction($account, '2026-07-20', -500.0, BankTransaction::FLOW_EXPENSE);
        $this->transaction($account, '2026-06-10', -300.0, BankTransaction::FLOW_EXPENSE);

        $this->get('/cashflow?month=2026-07-01')->assertInertia(fn (Assert $page) => $page
            ->has('monthlyFlow', 2)
            ->where('monthlyFlow.0.date', '2026-06-01')
            ->where('monthlyFlow.0.net', -300)
            ->where('monthlyFlow.1.date', '2026-07-01')
            ->where('monthlyFlow.1.income', 2000)
            ->where('monthlyFlow.1.expense', -500)
            ->where('monthlyFlow.1.net', 1500)
        );
    }

    public function test_monthly_flow_skips_transfers_and_excluded_rows(): void
    {
        $account = $this->account();
        $this->transaction($account, '2026-07-05', -100.0, BankTransaction::FLOW_EXPENSE);
        $this->transaction($account, '2026-07-06', -900.0, BankTransaction::FLOW_TRANSFER);
        $excluded = $this->transaction($account, '2026-07-07', -700.0, BankTransaction::FLOW_EXPENSE);
        $excluded->update(['excluded' => true]);

        $this->get('/cashflow?month=2026-07-01')->assertInertia(fn (Assert $page) => $page
            ->has('monthlyFlow', 1)
            ->where('monthlyFlow.0.net', -100)
        );
    }

    public function test_monthly_flow_counts_an_unclassified_row_as_an_expense(): void
    {
        // The page's effective() defaults a null flow_type to 'expense', so the
        // series has to agree or the chart wouldn't match the month's net.
        $account = $this->account();
        $this->transaction($account, '2026-07-05', -250.0, null);

        $this->get('/cashflow?month=2026-07-01')->assertInertia(fn (Assert $page) => $page
            ->where('monthlyFlow.0.expense', -250)
            ->where('monthlyFlow.0.net', -250)
        );
    }

    public function test_pending_review_counts_only_the_unreviewed_rows_of_the_month(): void
    {
        $account = $this->account();
        $this->transaction($account, '2026-07-05', -10.0, BankTransaction::FLOW_EXPENSE);
        $this->transaction($account, '2026-07-06', -20.0, BankTransaction::FLOW_EXPENSE);
        $this->transaction($account, '2026-07-07', -30.0, BankTransaction::FLOW_EXPENSE)
            ->update(['reviewed_at' => now()]);
        // Another month's pending row must not inflate this month's count.
        $this->transaction($account, '2026-06-07', -40.0, BankTransaction::FLOW_EXPENSE);

        $this->get('/cashflow?month=2026-07-01')->assertInertia(fn (Assert $page) => $page
            ->where('pendingReview', 2)
        );
    }

    public function test_reviewed_rows_still_count_towards_the_month_totals(): void
    {
        // The rows are sent whichever state they're in: the page computes the
        // month's net over the whole set, and only the dialog filters.
        $account = $this->account();
        $this->transaction($account, '2026-07-05', -100.0, BankTransaction::FLOW_EXPENSE)
            ->update(['reviewed_at' => now()]);

        $this->get('/cashflow?month=2026-07-01')->assertInertia(fn (Assert $page) => $page
            ->has('transactions', 1)
            ->where('transactions.0.reviewed', true)
            ->where('pendingReview', 0)
            ->where('monthlyFlow.0.net', -100)
        );
    }

    public function test_monthly_flow_is_empty_without_transactions(): void
    {
        $this->get('/cashflow')->assertInertia(fn (Assert $page) => $page
            ->where('monthlyFlow', [])
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

    public function test_does_not_carry_position_returns(): void
    {
        // The positions card is gone: its per-position detail already lives in
        // the per-asset TransactionsDialog reachable from the Bilancio, and its
        // aggregate in the Dashboard's PortfolioInsights. This page must not pay
        // for a whole-history, ISIN-deduplicated computation it doesn't render.
        $asset = Asset::factory()->create(['name' => 'ACWI', 'ticker' => 'ACWI', 'isin' => 'IE00B6R52259', 'date' => '2026-05-01']);
        Transaction::factory()->for($asset)->create([
            'type' => Transaction::TYPE_BUY, 'shares' => 10, 'price_per_share' => 100, 'date' => '2026-05-10',
        ]);
        AssetPrice::create(['ticker' => 'ACWI', 'price' => 120, 'currency' => 'EUR', 'fetched_at' => now()]);

        $this->get('/cashflow?month=2026-05-01')->assertInertia(fn (Assert $page) => $page
            ->missing('positionReturns')
        );
    }
}
