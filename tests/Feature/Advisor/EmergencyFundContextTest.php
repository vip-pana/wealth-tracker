<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Actions\Advisor\BuildAdvisorContext;
use App\Actions\Advisor\RenderAdvisorContext;
use App\Actions\Snapshots\StoreSnapshot;
use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyFundContextTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function buffer(float $value): void
    {
        // An investable position so the portfolio has data, plus a
        // non-investable buffer of the given value.
        $etf = Category::factory()->create(['macro_category' => MacroCategory::ETF, 'investable' => true]);
        $fund = Category::factory()->create(['macro_category' => MacroCategory::Liquidita, 'investable' => false]);
        Asset::factory()->create(['category_id' => $etf->id, 'value' => 20000, 'date' => '2026-07-31']);
        Asset::factory()->create(['category_id' => $fund->id, 'value' => $value, 'date' => '2026-07-31']);
        app(StoreSnapshot::class)->run('2026-07-31');
    }

    private function expense(float $amount, string $date): void
    {
        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut', 'aspsp_country' => 'IT',
            'state' => 'state-'.(++$this->seq), 'session_id' => 's', 'status' => BankConnection::STATUS_ACTIVE,
        ]);
        $account = $connection->accounts()->create(['uid' => 'acc-'.$this->seq, 'iban' => 'IT'.$this->seq]);
        BankTransaction::create([
            'bank_account_id' => $account->id, 'external_id' => 'tx-'.$this->seq,
            'amount' => -$amount, 'currency' => 'EUR', 'booking_date' => $date,
            'flow_type' => BankTransaction::FLOW_EXPENSE, 'excluded' => false,
        ]);
    }

    private function render(): string
    {
        return app(RenderAdvisorContext::class)->run(app(BuildAdvisorContext::class)->run());
    }

    public function test_reports_coverage_and_flags_a_fund_below_target(): void
    {
        $this->buffer(3000);
        // 1000/mo over two months → avg 1000; 3000 buffer = 3 months, under 6.
        $this->expense(1000, '2026-06-15');
        $this->expense(1000, '2026-07-15');

        $out = $this->render();

        $this->assertStringContainsString('FONDO DI EMERGENZA', $out);
        $this->assertStringContainsString('3 mesi', $out);
        $this->assertStringContainsString('obiettivo di 6 mesi', $out);
        $this->assertStringContainsString('SOTTO', $out);
    }

    public function test_confirms_a_fund_that_covers_the_target(): void
    {
        $this->buffer(12000);
        $this->expense(1000, '2026-06-15');
        $this->expense(1000, '2026-07-15');

        $out = $this->render();

        // 12000 / 1000 = 12 months, over the 6-month target.
        $this->assertStringContainsString('COPRE', $out);
        $this->assertStringNotContainsString('SOTTO', $out);
    }

    public function test_reports_only_the_amount_when_no_expenses_observed(): void
    {
        $this->buffer(3000);

        $out = $this->render();

        $this->assertStringContainsString('FONDO DI EMERGENZA', $out);
        $this->assertStringContainsString('non è calcolabile', $out);
        $this->assertStringNotContainsString('mesi di spese coperte', $out);
    }
}
