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
    }

    public function test_skips_facts_whose_data_is_missing(): void
    {
        $facts = $this->factsFor([
            'portfolio' => [
                'hasData' => true,
                'monthsTracked' => 1,
                'totalNetWorth' => 1000.0,
                'liquidity' => ['share_pct' => 0.0],
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
    }
}
