<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Actions\Advisor\BuildAdvisorContext;
use App\Advisor\Tools\AdvisorToolFactory;
use App\Models\Snapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Prism\Prism\Tool;
use RuntimeException;
use Tests\TestCase;

class AdvisorToolFactoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, mixed>
     */
    private array $portfolioContext = [
        'portfolio' => [
            'hasData' => true,
            'monthsTracked' => 6,
            'totalNetWorth' => 50000.0,
            'allocation' => [
                ['name' => 'Bitcoin', 'value' => 16000.0, 'share_pct' => 32.0],
                ['name' => 'Liquidità', 'value' => 10000.0, 'share_pct' => 20.0],
            ],
            'concentration' => ['hhi' => 1424.0, 'top_category' => 'Bitcoin', 'top_share_pct' => 32.0],
            'liquidity' => ['value' => 10000.0, 'share_pct' => 20.0],
            'volatility' => ['monthly_stddev_pct' => 4.2, 'best_month_pct' => 8.0, 'worst_month_pct' => -3.0],
            'goalEta' => [
                'reached' => false,
                'target_value' => 100000.0,
                'avg_monthly_gain' => 2000.0,
                'low_confidence' => false,
            ],
        ],
        'positionReturns' => [
            'positions' => [
                [
                    'id' => 1, 'name' => 'ACWI ETF', 'shares' => 153.05, 'average_cost' => 100.0,
                    'cost_basis' => 15305.0, 'current_value' => 18000.0, 'unrealised_pnl' => 2695.0,
                    'unrealised_pnl_pct' => 17.6, 'realised_pnl' => 0.0,
                ],
            ],
            'aggregate' => [],
        ],
    ];

    /**
     * A factory whose BuildAdvisorContext returns a fixed context, so the tools'
     * formatting/lookup is tested in isolation from the metric computation.
     *
     * @param  array<string, mixed>  $context
     */
    private function toolFor(array $context): AdvisorToolFactory
    {
        $build = Mockery::mock(BuildAdvisorContext::class);
        $build->shouldReceive('run')->andReturn($context);

        return new AdvisorToolFactory($build);
    }

    private function tool(AdvisorToolFactory $factory, string $name): Tool
    {
        foreach ($factory->make() as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        throw new RuntimeException("tool {$name} not found");
    }

    public function test_describes_a_matched_position_with_an_explicit_gain_sign(): void
    {
        $out = $this->tool($this->toolFor($this->portfolioContext), 'get_position')->handle(name: 'acwi');

        $this->assertStringContainsString('ACWI ETF', $out);
        $this->assertStringContainsString('+2.695,00€ (guadagno)', $out);
        $this->assertStringContainsString('+17,60%', $out);
    }

    public function test_lists_available_positions_when_the_name_has_no_match(): void
    {
        $out = $this->tool($this->toolFor($this->portfolioContext), 'get_position')->handle(name: 'tesla');

        $this->assertStringContainsString('Nessuna posizione trovata', $out);
        $this->assertStringContainsString('ACWI ETF', $out);
    }

    public function test_reports_no_positions_when_none_are_transaction_managed(): void
    {
        $out = $this->tool($this->toolFor(['positionReturns' => null]), 'get_position')->handle(name: 'acwi');

        $this->assertStringContainsString('Nessuna posizione gestita da transazioni', $out);
    }

    public function test_summarises_the_portfolio_with_allocation_and_idle_liquidity(): void
    {
        $out = $this->tool($this->toolFor($this->portfolioContext), 'get_portfolio_summary')->handle();

        $this->assertStringContainsString('50.000,00€', $out);
        $this->assertStringContainsString('Bitcoin', $out);
        $this->assertStringContainsString('32', $out);
        $this->assertStringContainsString('Liquidità ferma', $out);
    }

    public function test_reports_missing_data_for_the_summary_when_there_is_none(): void
    {
        $out = $this->tool($this->toolFor(['portfolio' => ['hasData' => false]]), 'get_portfolio_summary')->handle();

        $this->assertStringContainsStringIgnoringCase('non ci sono ancora dati', $out);
    }

    public function test_simulates_a_pac_with_compound_growth_and_reports_years(): void
    {
        // 50k → 100k with 5.000/mese and a 5% default assumption compounds to a
        // single-digit number of months; the reply states the return assumption
        // and expresses the ETA in years.
        $out = $this->tool($this->toolFor($this->portfolioContext), 'simulate_pac')->handle(monthly_amount: 5000);

        $this->assertStringContainsString('5.000,00€', $out);
        $this->assertStringContainsString('5%', $out);
        $this->assertStringContainsString('assunzione di pianificazione', $out);
        $this->assertStringContainsString('anni', $out);
    }

    public function test_uses_the_risk_profile_for_the_expected_return(): void
    {
        $ctx = $this->portfolioContext;
        $ctx['investorProfile'] = ['risk_tolerance' => 'high'];

        $out = $this->tool($this->toolFor($ctx), 'simulate_pac')->handle(monthly_amount: 5000);

        $this->assertStringContainsString('7%', $out);
        $this->assertStringContainsString('profilo di rischio alto', $out);
    }

    public function test_flags_a_low_confidence_pac_simulation(): void
    {
        $ctx = $this->portfolioContext;
        $ctx['portfolio']['goalEta']['low_confidence'] = true;

        $out = $this->tool($this->toolFor($ctx), 'simulate_pac')->handle(monthly_amount: 5000);

        $this->assertStringContainsString('pochi mesi di dati', $out);
    }

    public function test_reports_an_unreachable_goal_within_a_reasonable_horizon(): void
    {
        $ctx = $this->portfolioContext;
        // A target so far beyond the pace that even compounding can't reach it
        // within the 100-year cap.
        $ctx['portfolio']['totalNetWorth'] = 35000.0;
        $ctx['portfolio']['goalEta']['target_value'] = 100000000.0;

        $out = $this->tool($this->toolFor($ctx), 'simulate_pac')->handle(monthly_amount: 10);

        $this->assertStringContainsString('non viene raggiunto', $out);
        $this->assertStringContainsString('troppo ambizioso', $out);
    }

    public function test_computes_net_worth_change_between_two_snapshot_dates(): void
    {
        Snapshot::create(['date' => '2026-01-31', 'total_value' => 40000]);
        Snapshot::create(['date' => '2026-04-30', 'total_value' => 50000]);

        $out = $this->tool($this->toolFor($this->portfolioContext), 'net_worth_between')->handle(from: '2026-01-01', to: '2026-04-30');

        $this->assertStringContainsString('40.000,00€', $out);
        $this->assertStringContainsString('50.000,00€', $out);
        $this->assertStringContainsString('+10.000,00€ (guadagno)', $out);
        $this->assertStringContainsString('+25,00%', $out);
    }

    public function test_reports_no_snapshots_when_the_period_has_none(): void
    {
        $out = $this->tool($this->toolFor($this->portfolioContext), 'net_worth_between')->handle(from: '2020-01-01', to: '2020-12-31');

        $this->assertStringContainsString('Non ci sono snapshot', $out);
    }
}
