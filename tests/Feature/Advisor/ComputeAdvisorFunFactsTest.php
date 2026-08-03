<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Actions\Advisor\BuildAdvisorContext;
use App\Actions\Advisor\ComputeAdvisorFunFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComputeAdvisorFunFactsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private function factsFor(array $context): array
    {
        $stub = \Mockery::mock(BuildAdvisorContext::class);
        $stub->shouldReceive('run')->andReturn($context);

        return new ComputeAdvisorFunFacts($stub)->run();
    }

    public function test_returns_empty_when_there_is_no_portfolio_data(): void
    {
        $facts = $this->factsFor(['portfolio' => ['hasData' => false]]);

        $this->assertSame([], $facts);
    }

    public function test_builds_true_facts_from_the_context(): void
    {
        $facts = $this->factsFor([
            'portfolio' => [
                'hasData' => true,
                'monthsTracked' => 5,
                'totalNetWorth' => 120000.0,
                'allocationDrift' => [['name' => 'Azioni', 'delta_pp' => 8.4]],
                'concentration' => ['top_category' => 'Bitcoin', 'top_share_pct' => 32.0],
                'liquidity' => ['share_pct' => 18.0],
                'volatility' => ['monthly_stddev_pct' => 2.1, 'best_month_pct' => 6.0, 'worst_month_pct' => -3.5],
                'goalEta' => [
                    'reached' => false, 'months_to_goal' => 30, 'projected_date' => '2029-02-01',
                    'on_track' => true, 'low_confidence' => false,
                ],
            ],
            'positionReturns' => [
                'aggregate' => ['unrealised_pnl_pct' => 22.5],
                'positions' => [
                    ['name' => 'ACWI', 'unrealised_pnl_pct' => 12.0],
                    ['name' => 'Gold', 'unrealised_pnl_pct' => 30.0],
                ],
            ],
            'contribution' => ['monthly_avg' => 500.0, 'months' => 6],
            'costs' => ['annual_cost' => 42.0, 'weighted_ter_pct' => 0.2, 'covered_value' => 21000.0],
        ]);

        $joined = implode(' | ', $facts);

        $this->assertStringContainsString('Bitcoin', $joined);
        $this->assertStringContainsString('32', $joined);
        $this->assertStringContainsString('+22,5%', $joined);
        $this->assertStringContainsString('Gold', $joined);   // best position (30% > 12%)
        $this->assertStringContainsString('PAC', $joined);
        $this->assertStringContainsString('5 mesi', $joined);
        $this->assertStringContainsString('Azioni', $joined);  // drift
        $this->assertStringContainsString('2,1%', $joined);    // volatility
        $this->assertStringContainsString('+6%', $joined);     // best month
        $this->assertStringContainsString('-3,5%', $joined);   // worst month
        $this->assertStringContainsString('2 anni', $joined);  // goal ETA, 30 months
        $this->assertStringContainsString('in linea con la data', $joined);
    }

    public function test_reports_a_reached_goal_instead_of_an_eta(): void
    {
        $facts = $this->factsFor([
            'portfolio' => [
                'hasData' => true,
                'totalNetWorth' => 200000.0,
                'goalEta' => ['reached' => true, 'target_value' => 150000.0],
            ],
        ]);

        $joined = implode(' | ', $facts);

        $this->assertStringContainsString('già raggiunto', $joined);
        $this->assertStringNotContainsString('Al ritmo attuale', $joined);
    }

    public function test_reports_a_flat_trajectory_when_there_is_no_projection(): void
    {
        $facts = $this->factsFor([
            'portfolio' => [
                'hasData' => true,
                'totalNetWorth' => 1000.0,
                'goalEta' => ['reached' => false, 'on_track' => false, 'low_confidence' => false],
            ],
        ]);

        $this->assertStringContainsString('non ti stai avvicinando', implode(' | ', $facts));
    }

    public function test_flags_a_low_confidence_goal_estimate(): void
    {
        $facts = $this->factsFor([
            'portfolio' => [
                'hasData' => true,
                'totalNetWorth' => 1000.0,
                'goalEta' => [
                    'reached' => false, 'months_to_goal' => 8,
                    'on_track' => null, 'low_confidence' => true,
                ],
            ],
        ]);

        $joined = implode(' | ', $facts);

        $this->assertStringContainsString('8 mesi', $joined);
        $this->assertStringContainsString('con le molle', $joined);
    }

    public function test_skips_facts_whose_data_is_missing(): void
    {
        $facts = $this->factsFor([
            'portfolio' => [
                'hasData' => true,
                'monthsTracked' => 1,
                'totalNetWorth' => 1000.0,
                'liquidity' => ['share_pct' => 0.0],
                // Fewer than three snapshots: no stddev to report.
                'volatility' => ['monthly_stddev_pct' => null, 'best_month_pct' => null, 'worst_month_pct' => null],
                'goalEta' => null,
            ],
            'positionReturns' => null,
            'contribution' => null,
            'costs' => null,
        ]);

        $joined = implode(' | ', $facts);

        $this->assertStringContainsString('1 mese', $joined);
        $this->assertStringNotContainsString('liquidità', $joined);   // 0% skipped
        $this->assertStringNotContainsString('PAC', $joined);
        $this->assertStringNotContainsString('rendimento vero', $joined);
        $this->assertStringNotContainsString('al mese:', $joined);    // volatility skipped
        $this->assertStringNotContainsString('obiettivo', $joined);   // goal ETA skipped
    }
}
