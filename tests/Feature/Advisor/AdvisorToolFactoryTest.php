<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Actions\Advisor\BuildAdvisorContext;
use App\Advisor\Tools\AdvisorToolActivityReporter;
use App\Advisor\Tools\AdvisorToolFactory;
use App\Advisor\Tools\AdvisorWidgetCollector;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use App\Models\Category;
use App\Models\Goal;
use App\Models\GoalCategoryAllocation;
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

        return new AdvisorToolFactory($build, new AdvisorToolActivityReporter, new AdvisorWidgetCollector);
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

    public function test_reports_live_tool_activity_to_the_armed_message(): void
    {
        $reporter = new AdvisorToolActivityReporter;
        $build = Mockery::mock(BuildAdvisorContext::class);
        $build->shouldReceive('run')->andReturn($this->portfolioContext);
        $factory = new AdvisorToolFactory($build, $reporter, new AdvisorWidgetCollector);

        $session = AdvisorSession::create(['kind' => 'chat', 'title' => 't', 'status' => 'pending']);
        $message = AdvisorMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => '', 'status' => 'pending']);

        $reporter->for($message);
        $this->tool($factory, 'get_position')->handle(name: 'ACWI');

        $this->assertSame('Sto controllando la tua posizione ACWI…', $message->fresh()?->tool_activity);
    }

    public function test_simulate_pac_emits_a_pac_simulator_widget_when_armed(): void
    {
        $collector = new AdvisorWidgetCollector;
        $build = Mockery::mock(BuildAdvisorContext::class);
        $build->shouldReceive('run')->andReturn($this->portfolioContext);
        $factory = new AdvisorToolFactory($build, new AdvisorToolActivityReporter, $collector);

        $session = AdvisorSession::create(['kind' => 'chat', 'title' => 't', 'status' => 'pending']);
        $message = AdvisorMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => '', 'status' => 'pending']);

        $collector->for($message);
        $this->tool($factory, 'simulate_pac')->handle(monthly_amount: 600);

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('pac_simulator', $widgets[0]['type']);
        $this->assertSame(50000.0, $widgets[0]['data']['current_net_worth']);
        $this->assertSame(100000.0, $widgets[0]['data']['target_value']);
        $this->assertSame(600.0, $widgets[0]['data']['monthly_amount']);
    }

    public function test_simulate_pac_emits_no_widget_when_there_is_no_goal(): void
    {
        $context = $this->portfolioContext;
        $context['portfolio']['goalEta'] = null;

        $collector = new AdvisorWidgetCollector;
        $build = Mockery::mock(BuildAdvisorContext::class);
        $build->shouldReceive('run')->andReturn($context);
        $factory = new AdvisorToolFactory($build, new AdvisorToolActivityReporter, $collector);

        $session = AdvisorSession::create(['kind' => 'chat', 'title' => 't', 'status' => 'pending']);
        $message = AdvisorMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => '', 'status' => 'pending']);

        $collector->for($message);
        $this->tool($factory, 'simulate_pac')->handle(monthly_amount: 600);

        $this->assertSame([], $collector->widgets());
    }

    public function test_widget_collector_is_a_no_op_when_not_armed(): void
    {
        $collector = new AdvisorWidgetCollector;
        $this->tool($this->toolFor($this->portfolioContext), 'simulate_pac')->handle(monthly_amount: 600);

        $this->assertSame([], $collector->widgets());
    }

    public function test_get_position_emits_a_managed_position_card(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);

        $this->tool($factory, 'get_position')->handle(name: 'acwi');

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('position_card', $widgets[0]['type']);
        $this->assertTrue($widgets[0]['data']['managed']);
        $this->assertSame('ACWI ETF', $widgets[0]['data']['name']);
        $this->assertSame(2695.0, $widgets[0]['data']['unrealised_pnl']);
    }

    public function test_get_position_emits_an_unmanaged_category_card(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);

        $this->tool($factory, 'get_position')->handle(name: 'bitcoin');

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('position_card', $widgets[0]['type']);
        $this->assertFalse($widgets[0]['data']['managed']);
        $this->assertSame(16000.0, $widgets[0]['data']['current_value']);
    }

    public function test_get_portfolio_summary_emits_an_allocation_donut_with_colours(): void
    {
        Category::factory()->create(['name' => 'Bitcoin', 'color' => '#f7931a']);

        [$factory, $collector] = $this->armedFactory($this->portfolioContext);

        $this->tool($factory, 'get_portfolio_summary')->handle();

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('allocation_donut', $widgets[0]['type']);
        $bitcoin = collect($widgets[0]['data']['slices'])->firstWhere('name', 'Bitcoin');
        $this->assertSame('#f7931a', $bitcoin['color']);
        // A slice without a matching category falls back to the neutral grey.
        $liquid = collect($widgets[0]['data']['slices'])->firstWhere('name', 'Liquidità');
        $this->assertSame('#94a3b8', $liquid['color']);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: AdvisorToolFactory, 1: AdvisorWidgetCollector}
     */
    private function armedFactory(array $context): array
    {
        $collector = new AdvisorWidgetCollector;
        $build = Mockery::mock(BuildAdvisorContext::class);
        $build->shouldReceive('run')->andReturn($context);
        $factory = new AdvisorToolFactory($build, new AdvisorToolActivityReporter, $collector);

        $session = AdvisorSession::create(['kind' => 'chat', 'title' => 't', 'status' => 'pending']);
        $message = AdvisorMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => '', 'status' => 'pending']);
        $collector->for($message);
        // Most tests exercise tools other than the consent-gated proposals; allow
        // them by default so those stay unaffected. The gates have their own tests.
        $collector->allowProfileProposal(true);
        $collector->allowGoalProposal(true);
        $collector->allowProfileOffer(true);
        $collector->allowGoalOffer(true);
        $collector->allowProfileFact(true);

        return [$factory, $collector];
    }

    public function test_does_not_report_activity_when_not_armed(): void
    {
        // The tools run outside a tracked turn (report/tests): report() is a no-op.
        $out = $this->tool($this->toolFor($this->portfolioContext), 'get_portfolio_summary')->handle();

        $this->assertStringContainsString('Patrimonio netto totale', $out);
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

    public function test_falls_back_to_the_allocation_for_a_non_transaction_managed_category(): void
    {
        // Bitcoin is in the allocation but has no imported transactions, so it
        // has a current value/weight but no cost basis or real return.
        $out = $this->tool($this->toolFor($this->portfolioContext), 'get_position')->handle(name: 'bitcoin');

        $this->assertStringContainsString('Bitcoin', $out);
        $this->assertStringContainsString('16.000,00€', $out);
        $this->assertStringContainsString('non è gestita da transazioni', $out);
    }

    public function test_reports_no_match_when_neither_positions_nor_allocation_have_it(): void
    {
        $out = $this->tool($this->toolFor(['positionReturns' => null, 'portfolio' => ['hasData' => false]]), 'get_position')->handle(name: 'acwi');

        $this->assertStringContainsString('Nessuna posizione trovata', $out);
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

    public function test_simulate_pac_models_a_growing_contribution(): void
    {
        // A step-up PAC reaches the goal sooner than a flat one, and the reply
        // states the annual growth. Uses a modest amount so the growth matters.
        $flat = $this->tool($this->toolFor($this->portfolioContext), 'simulate_pac')->handle(monthly_amount: 500);
        $growing = $this->tool($this->toolFor($this->portfolioContext), 'simulate_pac')->handle(monthly_amount: 500, annual_increase_pct: 10);

        $this->assertStringContainsString('in crescita del 10', $growing);

        // Extract the month count from each reply ("N mesi") and compare.
        preg_match('/(\d+) mesi/', $flat, $flatM);
        preg_match('/(\d+) mesi/', $growing, $growM);
        $this->assertNotEmpty($flatM);
        $this->assertNotEmpty($growM);
        $this->assertLessThan((int) $flatM[1], (int) $growM[1]);

        // The growing reply spells out the per-year contribution schedule so the
        // model doesn't hand-compute it: year 1 is 500/mo, year 2 is +10%.
        $this->assertStringContainsString('Piano dei versamenti', $growing);
        $this->assertStringContainsString('Anno 1: 500,00€/mese', $growing);
        $this->assertStringContainsString('Anno 2: 550,00€/mese', $growing);
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

    public function test_net_worth_between_emits_a_line_widget_with_the_snapshot_points(): void
    {
        Snapshot::create(['date' => '2026-01-31', 'total_value' => 40000]);
        Snapshot::create(['date' => '2026-02-28', 'total_value' => 45000]);
        Snapshot::create(['date' => '2026-04-30', 'total_value' => 50000]);

        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $this->tool($factory, 'net_worth_between')->handle(from: '2026-01-31', to: '2026-04-30');

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('networth_line', $widgets[0]['type']);
        $this->assertCount(3, $widgets[0]['data']['points']);
        $this->assertSame('2026-01-31', $widgets[0]['data']['from']);
    }

    public function test_net_worth_between_emits_no_widget_with_a_single_point(): void
    {
        Snapshot::create(['date' => '2026-04-30', 'total_value' => 50000]);

        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        // Both endpoints resolve to the one snapshot, so there's no curve to draw.
        $this->tool($factory, 'net_worth_between')->handle(from: '2026-04-01', to: '2026-04-30');

        $this->assertSame([], $collector->widgets());
    }

    public function test_allocation_vs_target_emits_a_comparison_widget(): void
    {
        $azioni = Category::factory()->create(['name' => 'Azioni']);
        $goal = Goal::create(['name' => 'FIRE', 'target_value' => 100000, 'target_date' => '2040-01-01']);
        GoalCategoryAllocation::create(['goal_id' => $goal->id, 'category_id' => $azioni->id, 'percentage' => 60]);

        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $this->tool($factory, 'allocation_vs_target')->handle();

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('allocation_vs_target', $widgets[0]['type']);
        // Bitcoin is in the current allocation but not the target -> target 0.
        $bitcoin = collect($widgets[0]['data']['rows'])->firstWhere('name', 'Bitcoin');
        $this->assertSame(32.0, $bitcoin['current_pct']);
        $this->assertSame(0.0, $bitcoin['target_pct']);
        // Azioni is in the target but not the current allocation -> current 0.
        $azioniRow = collect($widgets[0]['data']['rows'])->firstWhere('name', 'Azioni');
        $this->assertSame(60.0, $azioniRow['target_pct']);
    }

    public function test_allocation_vs_target_reports_no_target_when_none_set(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $out = $this->tool($factory, 'allocation_vs_target')->handle();

        $this->assertStringContainsString('non posso confrontare con un target', $out);
        $this->assertSame([], $collector->widgets());
    }

    public function test_list_positions_emits_a_table_widget(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $out = $this->tool($factory, 'list_positions')->handle();

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('positions_table', $widgets[0]['type']);
        $this->assertCount(1, $widgets[0]['data']['rows']);
        $this->assertSame('ACWI ETF', $widgets[0]['data']['rows'][0]['name']);
        $this->assertStringContainsString('ACWI ETF', $out);
    }

    public function test_simulate_goal_emits_a_widget_with_the_required_monthly(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $this->tool($factory, 'simulate_goal')->handle(target_value: 1000000, target_date: '2050-01-01');

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('goal_simulator', $widgets[0]['type']);
        $this->assertSame(1000000.0, $widgets[0]['data']['target_value']);
        $this->assertGreaterThan(0.0, $widgets[0]['data']['required_monthly']);
    }

    public function test_simulate_goal_rejects_a_past_date(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $out = $this->tool($factory, 'simulate_goal')->handle(target_value: 1000000, target_date: '2000-01-01');

        $this->assertStringContainsString('futura', $out);
        $this->assertSame([], $collector->widgets());
    }

    public function test_propose_profile_update_emits_a_proposal_widget(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $this->tool($factory, 'propose_profile_update')->handle(
            horizon: 'long',
            risk_tolerance: 'medium',
        );

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('profile_proposal', $widgets[0]['type']);
        $this->assertSame('long', $widgets[0]['data']['horizon']);
        $this->assertSame('medium', $widgets[0]['data']['risk_tolerance']);
        // Fields not proposed are absent, not null.
        $this->assertArrayNotHasKey('notes', $widgets[0]['data']);
    }

    public function test_propose_profile_update_drops_an_invalid_enum_value(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        // The model must not be able to push an out-of-range enum into the DB.
        $this->tool($factory, 'propose_profile_update')->handle(
            horizon: 'forever',
            risk_tolerance: 'medium',
        );

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertArrayNotHasKey('horizon', $widgets[0]['data']);
        $this->assertSame('medium', $widgets[0]['data']['risk_tolerance']);
    }

    public function test_propose_profile_update_emits_nothing_when_all_fields_empty(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $out = $this->tool($factory, 'propose_profile_update')->handle(horizon: null, risk_tolerance: null);

        $this->assertStringContainsString('Non ho abbastanza elementi', $out);
        $this->assertSame([], $collector->widgets());
    }

    public function test_propose_profile_update_emits_nothing_without_consent(): void
    {
        // Same setup as armedFactory but WITHOUT allowing the proposal.
        $collector = new AdvisorWidgetCollector;
        $build = Mockery::mock(BuildAdvisorContext::class);
        $build->shouldReceive('run')->andReturn($this->portfolioContext);
        $factory = new AdvisorToolFactory($build, new AdvisorToolActivityReporter, $collector);
        $session = AdvisorSession::create(['kind' => 'chat', 'title' => 't', 'status' => 'pending']);
        $message = AdvisorMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => '', 'status' => 'pending']);
        $collector->for($message);
        // proposal NOT allowed (default)

        $out = $this->tool($factory, 'propose_profile_update')->handle(horizon: 'long', risk_tolerance: 'high');

        $this->assertSame([], $collector->widgets());
        $this->assertStringContainsString('CHIEDI', $out);
    }

    public function test_propose_profile_update_carries_the_risk_profiling_notes(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $this->tool($factory, 'propose_profile_update')->handle(
            risk_tolerance: 'medium',
            notes: 'Orizzonte 15+ anni, reddito stabile, ma nervoso oltre il -20%.',
        );

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('medium', $widgets[0]['data']['risk_tolerance']);
        $this->assertStringContainsString('nervoso', $widgets[0]['data']['notes']);
    }

    public function test_propose_profile_update_carries_income_and_emergency_fund(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $this->tool($factory, 'propose_profile_update')->handle(
            income_monthly: 2000,
            emergency_fund: 'none',
        );

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame(2000.0, $widgets[0]['data']['income_monthly']);
        $this->assertSame('none', $widgets[0]['data']['emergency_fund']);
    }

    public function test_propose_profile_update_drops_an_invalid_emergency_fund(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $this->tool($factory, 'propose_profile_update')->handle(
            risk_tolerance: 'high',
            emergency_fund: 'invalid',
        );

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertArrayNotHasKey('emergency_fund', $widgets[0]['data']);
    }

    public function test_confirm_profile_fact_emits_a_confirmation_card_directly(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $out = $this->tool($factory, 'confirm_profile_fact')->handle(income_monthly: 2000);

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('profile_proposal', $widgets[0]['type']);
        $this->assertSame(2000.0, $widgets[0]['data']['income_monthly']);
        $this->assertStringContainsString('Applica', $out);
    }

    public function test_confirm_profile_fact_emits_nothing_when_not_allowed(): void
    {
        // Same as armedFactory but without opening the profile-fact gate.
        $collector = new AdvisorWidgetCollector;
        $build = Mockery::mock(BuildAdvisorContext::class);
        $build->shouldReceive('run')->andReturn($this->portfolioContext);
        $factory = new AdvisorToolFactory($build, new AdvisorToolActivityReporter, $collector);
        $session = AdvisorSession::create(['kind' => 'chat', 'title' => 't', 'status' => 'pending']);
        $message = AdvisorMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => '', 'status' => 'pending']);
        $collector->for($message);
        // profile-fact gate NOT opened (default closed)

        $this->tool($factory, 'confirm_profile_fact')->handle(income_monthly: 2000);

        $this->assertSame([], $collector->widgets());
    }

    public function test_confirm_profile_fact_drops_an_invalid_emergency_fund(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $this->tool($factory, 'confirm_profile_fact')->handle(emergency_fund: 'gold-bars', income_monthly: 1800);

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertArrayNotHasKey('emergency_fund', $widgets[0]['data']);
        $this->assertSame(1800.0, $widgets[0]['data']['income_monthly']);
    }

    public function test_propose_goal_core_emits_a_proposal_widget(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $this->tool($factory, 'propose_goal_core')->handle(
            target_value: 1000000,
            target_date: '2099-12-31',
            description: 'Primo milione per libertà finanziaria',
        );

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('goal_core_proposal', $widgets[0]['type']);
        $this->assertSame(1000000.0, $widgets[0]['data']['target_value']);
        $this->assertSame('2099-12-31', $widgets[0]['data']['target_date']);
        $this->assertStringContainsString('libertà', $widgets[0]['data']['description']);
    }

    public function test_propose_goal_core_rejects_a_past_target_date(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $out = $this->tool($factory, 'propose_goal_core')->handle(
            target_value: 1000000,
            target_date: '2000-01-01',
        );

        $this->assertSame([], $collector->widgets());
        $this->assertStringContainsString('non è valida o non è futura', $out);
    }

    public function test_propose_goal_core_emits_nothing_without_consent(): void
    {
        $collector = new AdvisorWidgetCollector;
        $build = Mockery::mock(BuildAdvisorContext::class);
        $build->shouldReceive('run')->andReturn($this->portfolioContext);
        $factory = new AdvisorToolFactory($build, new AdvisorToolActivityReporter, $collector);
        $session = AdvisorSession::create(['kind' => 'chat', 'title' => 't', 'status' => 'pending']);
        $message = AdvisorMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => '', 'status' => 'pending']);
        $collector->for($message);
        // goal proposal NOT allowed (default)

        $out = $this->tool($factory, 'propose_goal_core')->handle(target_value: 1000000);

        $this->assertSame([], $collector->widgets());
        $this->assertStringContainsString('CHIEDI', $out);
    }

    public function test_propose_goal_milestones_drops_invalid_entries_and_maps_label(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $this->tool($factory, 'propose_goal_milestones')->handle(milestones: [
            ['label' => 'Metà percorso', 'action' => 'Sposta il 5% da Bitcoin a Obbligazioni.', 'rationale' => 'Riduce la volatilità avvicinandosi al target.', 'target_value' => 500000, 'target_date' => '2099-06-30'],
            ['label' => 'Nel passato', 'target_value' => 250000, 'target_date' => '2000-01-01'], // dropped: past
            ['label' => 'Senza valore', 'target_value' => 0, 'target_date' => '2099-01-01'],     // dropped: non-positive
        ]);

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('goal_milestones_proposal', $widgets[0]['type']);
        $this->assertCount(1, $widgets[0]['data']['milestones']);
        $this->assertSame('Metà percorso', $widgets[0]['data']['milestones'][0]['label']);
        $this->assertSame('Sposta il 5% da Bitcoin a Obbligazioni.', $widgets[0]['data']['milestones'][0]['action']);
        $this->assertSame('Riduce la volatilità avvicinandosi al target.', $widgets[0]['data']['milestones'][0]['rationale']);
        $this->assertSame(500000.0, $widgets[0]['data']['milestones'][0]['target_value']);
        // No allocation given → degrades to an empty glide-path step.
        $this->assertSame([], $widgets[0]['data']['milestones'][0]['allocation']);
    }

    public function test_propose_goal_milestones_carries_a_valid_per_milestone_allocation(): void
    {
        Category::factory()->create(['name' => 'Azioni']);
        Category::factory()->create(['name' => 'Liquidità']);
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);

        $this->tool($factory, 'propose_goal_milestones')->handle(milestones: [
            ['label' => 'Metà', 'target_value' => 500000, 'target_date' => '2099-06-30', 'allocation' => [
                ['category' => 'Azioni', 'percentage' => 70],
                ['category' => 'Liquidità', 'percentage' => 30],
            ]],
        ]);

        $alloc = $collector->widgets()[0]['data']['milestones'][0]['allocation'];
        $this->assertCount(2, $alloc);
        $this->assertSame('Azioni', $alloc[0]['category']);
        $this->assertSame(70.0, $alloc[0]['percentage']);
    }

    public function test_propose_goal_milestones_drops_an_allocation_that_is_not_100(): void
    {
        Category::factory()->create(['name' => 'Azioni']);
        Category::factory()->create(['name' => 'Liquidità']);
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);

        // Sums to 80 → the whole allocation degrades to [] (milestone still kept).
        $this->tool($factory, 'propose_goal_milestones')->handle(milestones: [
            ['label' => 'Metà', 'target_value' => 500000, 'target_date' => '2099-06-30', 'allocation' => [
                ['category' => 'Azioni', 'percentage' => 50],
                ['category' => 'Liquidità', 'percentage' => 30],
            ]],
        ]);

        $this->assertSame([], $collector->widgets()[0]['data']['milestones'][0]['allocation']);
    }

    public function test_propose_goal_composition_refuses_a_composition_that_is_not_100(): void
    {
        Category::factory()->create(['name' => 'Azioni']);
        Category::factory()->create(['name' => 'Liquidità']);
        Category::factory()->create(['name' => 'Oro']);
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);

        // The model dropped Oro, so the sum is 90 — the tool must refuse and emit
        // no card, telling the model to recompute to 100% with every category.
        $out = $this->tool($factory, 'propose_goal_composition')->handle(
            buckets: [
                ['category' => 'Azioni', 'percentage' => 70],
                ['category' => 'Liquidità', 'percentage' => 20],
            ],
            rationale: 'Peso azionario alto coerente con orizzonte lungo.',
        );

        $this->assertSame([], $collector->widgets());
        $this->assertStringContainsString('100%', $out);
    }

    public function test_propose_goal_composition_emits_the_card_when_it_sums_to_100(): void
    {
        Category::factory()->create(['name' => 'Azioni']);
        Category::factory()->create(['name' => 'Liquidità']);
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);

        $this->tool($factory, 'propose_goal_composition')->handle(
            buckets: [
                ['category' => 'Azioni', 'percentage' => 70],
                ['category' => 'Liquidità', 'percentage' => 30],
            ],
            rationale: 'Peso azionario alto coerente con orizzonte lungo.',
        );

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('goal_composition_proposal', $widgets[0]['type']);
        $this->assertCount(2, $widgets[0]['data']['buckets']);
        $this->assertSame(100.0, $widgets[0]['data']['total_pct']);
        $this->assertStringContainsString('orizzonte', $widgets[0]['data']['rationale']);
    }

    public function test_propose_goal_composition_drops_unknown_categories(): void
    {
        Category::factory()->create(['name' => 'Azioni']);
        Category::factory()->create(['name' => 'Liquidità']);
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);

        // An unknown category is dropped; the remaining two still sum to 100.
        $this->tool($factory, 'propose_goal_composition')->handle(buckets: [
            ['category' => 'Azioni', 'percentage' => 70],
            ['category' => 'Liquidità', 'percentage' => 30],
            ['category' => 'Inesistente', 'percentage' => 50],
        ]);

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertCount(2, $widgets[0]['data']['buckets']);
    }

    public function test_offer_profile_proposal_emits_a_proposal_offer_button(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $out = $this->tool($factory, 'offer_profile_proposal')->handle();

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('proposal_offer', $widgets[0]['type']);
        $this->assertSame('profile', $widgets[0]['data']['kind']);
        // The offer only shows a button; it must not itself claim to have proposed.
        $this->assertStringContainsString('pulsante', mb_strtolower($out));
    }

    public function test_offer_goal_proposal_emits_a_goal_offer_button(): void
    {
        [$factory, $collector] = $this->armedFactory($this->portfolioContext);
        $this->tool($factory, 'offer_goal_proposal')->handle();

        $widgets = $collector->widgets();
        $this->assertCount(1, $widgets);
        $this->assertSame('proposal_offer', $widgets[0]['type']);
        $this->assertSame('goal', $widgets[0]['data']['kind']);
    }

    public function test_offer_is_gated_to_interview_sessions(): void
    {
        // The offer button must NOT appear in a plain chat: with the offer gate
        // closed, the tool emits no widget and tells the model to answer normally.
        // (In production the gate is opened only for a goal/profile interview.)
        $collector = new AdvisorWidgetCollector;
        $build = Mockery::mock(BuildAdvisorContext::class);
        $build->shouldReceive('run')->andReturn($this->portfolioContext);
        $factory = new AdvisorToolFactory($build, new AdvisorToolActivityReporter, $collector);
        $session = AdvisorSession::create(['kind' => 'chat', 'title' => 't', 'status' => 'pending']);
        $message = AdvisorMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => '', 'status' => 'pending']);
        $collector->for($message);
        // offer gate NOT armed

        $out = $this->tool($factory, 'offer_goal_proposal')->handle();

        $this->assertSame([], $collector->widgets());
        $this->assertStringContainsString('non è una sessione', mb_strtolower($out));
    }

    public function test_goal_proposal_is_gated_separately_from_the_profile_flag(): void
    {
        $collector = new AdvisorWidgetCollector;
        $build = Mockery::mock(BuildAdvisorContext::class);
        $build->shouldReceive('run')->andReturn($this->portfolioContext);
        $factory = new AdvisorToolFactory($build, new AdvisorToolActivityReporter, $collector);
        $session = AdvisorSession::create(['kind' => 'chat', 'title' => 't', 'status' => 'pending']);
        $message = AdvisorMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => '', 'status' => 'pending']);
        $collector->for($message);
        // Consent to the PROFILE must not unlock a GOAL proposal.
        $collector->allowProfileProposal(true);

        $out = $this->tool($factory, 'propose_goal_core')->handle(target_value: 1000000);

        $this->assertSame([], $collector->widgets());
        $this->assertStringContainsString('CHIEDI', $out);
    }
}
