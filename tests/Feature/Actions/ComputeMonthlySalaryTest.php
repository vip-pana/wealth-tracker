<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Transactions\ComputeMonthlySalary;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComputeMonthlySalaryTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function salary(float $amount, string $date, string $note = 'STIPENDIO O PENSIONE WEROAD S.P.A.', bool $excluded = false): void
    {
        $this->tx($amount, $date, $note, BankTransaction::FLOW_INCOME, $excluded);
    }

    private function tx(float $amount, string $date, string $note, string $flow, bool $excluded = false): void
    {
        $connection = BankConnection::create([
            'aspsp_name' => 'Isybank', 'aspsp_country' => 'IT',
            'state' => 'state-'.(++$this->seq), 'session_id' => 's', 'status' => BankConnection::STATUS_ACTIVE,
        ]);
        $account = $connection->accounts()->create(['uid' => 'acc-'.$this->seq, 'iban' => 'IT'.$this->seq]);
        BankTransaction::create([
            'bank_account_id' => $account->id, 'external_id' => 'tx-'.$this->seq,
            'amount' => $amount, 'currency' => 'EUR', 'booking_date' => $date,
            'flow_type' => $flow, 'excluded' => $excluded,
            'raw' => ['remittance_information' => [$note]],
        ]);
    }

    public function test_null_when_no_salary_observed(): void
    {
        $this->assertNull(app(ComputeMonthlySalary::class)->run());
    }

    public function test_averages_over_distinct_salary_months(): void
    {
        $this->salary(1726, '2026-04-28');
        $this->salary(1738, '2026-05-28');
        $this->salary(3090, '2026-06-29');

        // 6554 / 3 distinct months, not / a wider span.
        $this->assertSame(2184.67, app(ComputeMonthlySalary::class)->run());
    }

    public function test_counts_a_month_once_even_with_two_salary_credits(): void
    {
        $this->salary(1000, '2026-04-10');
        $this->salary(1000, '2026-04-28');

        // Same month → divide by 1, so 2000, not 1000.
        $this->assertSame(2000.0, app(ComputeMonthlySalary::class)->run());
    }

    public function test_ignores_non_salary_income_and_excluded_rows(): void
    {
        $this->salary(2000, '2026-04-28');
        $this->tx(500, '2026-04-15', 'Payment from Lorenzo Germano', BankTransaction::FLOW_INCOME);
        $this->salary(9999, '2026-05-28', excluded: true);

        // Only the single non-excluded salary counts.
        $this->assertSame(2000.0, app(ComputeMonthlySalary::class)->run());
    }
}
