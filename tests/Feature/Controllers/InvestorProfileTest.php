<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Actions\Advisor\BuildAdvisorContext;
use App\Models\Category;
use App\Models\Goal;
use App\Models\InvestorProfile;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestorProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_stores_the_profile_as_a_single_row(): void
    {
        $this->post('/advisor/profile', [
            'horizon' => 'long',
            'risk_tolerance' => 'high',
            'notes' => 'Tollera bene i cali.',
        ])->assertRedirect();

        $this->assertDatabaseCount('investor_profile', 1);
        $this->assertDatabaseHas('investor_profile', ['horizon' => 'long', 'risk_tolerance' => 'high']);
    }

    public function test_updates_in_place_instead_of_inserting(): void
    {
        InvestorProfile::create(['horizon' => 'short', 'risk_tolerance' => 'low']);

        $this->post('/advisor/profile', ['horizon' => 'long', 'risk_tolerance' => 'high']);

        $this->assertDatabaseCount('investor_profile', 1);
        $this->assertSame('long', InvestorProfile::first()?->horizon);
    }

    public function test_rejects_invalid_enum_values(): void
    {
        $this->post('/advisor/profile', ['horizon' => 'forever'])
            ->assertSessionHasErrors('horizon');
    }

    public function test_context_includes_the_profile_when_set(): void
    {
        InvestorProfile::create(['horizon' => 'long', 'risk_tolerance' => 'high', 'notes' => 'Tollera i cali.']);

        $context = app(BuildAdvisorContext::class)->run();

        $this->assertNotNull($context['investorProfile']);
        $this->assertSame('long', $context['investorProfile']['horizon']);
        $this->assertSame('Tollera i cali.', $context['investorProfile']['notes']);
    }

    public function test_income_is_not_a_profile_field(): void
    {
        // Net income is observed from bank transactions (ComputeMonthlySalary),
        // never stored on the profile. Posting it is silently ignored, and the
        // context exposes net_monthly_income (null with no transactions), not
        // the old hand-entered income_monthly.
        $this->post('/advisor/profile', [
            'income_monthly' => 2000,
            'horizon' => 'long',
        ])->assertRedirect();

        $this->assertDatabaseHas('investor_profile', ['horizon' => 'long']);

        $context = app(BuildAdvisorContext::class)->run();
        $this->assertArrayNotHasKey('income_monthly', $context['investorProfile']);
        $this->assertNull($context['investorProfile']['net_monthly_income']);
    }

    public function test_stores_the_personal_fields(): void
    {
        $this->post('/advisor/profile', [
            'name' => 'Mario',
            'birth_date' => '1990-05-14',
            'memory' => 'Preferisce ETF ad accumulo.',
        ])->assertRedirect();

        $this->assertDatabaseHas('investor_profile', [
            'name' => 'Mario',
            'memory' => 'Preferisce ETF ad accumulo.',
        ]);
        $this->assertSame('1990-05-14', InvestorProfile::first()?->birth_date?->format('Y-m-d'));
    }

    public function test_rejects_a_future_birth_date(): void
    {
        $this->post('/advisor/profile', ['birth_date' => now()->addYear()->format('Y-m-d')])
            ->assertSessionHasErrors('birth_date');
    }

    public function test_context_derives_age_from_birth_date_and_carries_name_and_memory(): void
    {
        InvestorProfile::create([
            'name' => 'Mario',
            'birth_date' => now()->subYears(35)->format('Y-m-d'),
            'memory' => 'Non vuole obbligazioni.',
        ]);

        $context = app(BuildAdvisorContext::class)->run();

        $this->assertSame('Mario', $context['investorProfile']['name']);
        $this->assertSame(35, $context['investorProfile']['age']);
        $this->assertSame('Non vuole obbligazioni.', $context['investorProfile']['memory']);
    }

    public function test_context_profile_is_null_when_unset(): void
    {
        $context = app(BuildAdvisorContext::class)->run();

        $this->assertNull($context['investorProfile']);
    }

    public function test_goal_context_carries_the_objective_from_the_goal(): void
    {
        Goal::create(['name' => 'Il primo milione', 'target_value' => 1000000, 'target_date' => '2035-01-01']);

        $context = app(BuildAdvisorContext::class)->run();

        $this->assertNotNull($context['goal']);
        $this->assertSame('Il primo milione', $context['goal']['name']);
        $this->assertSame(1000000.0, $context['goal']['target_value']);
        $this->assertSame('2035', $context['goal']['target_year']);
    }

    public function test_goal_context_is_null_when_no_goal_exists(): void
    {
        $context = app(BuildAdvisorContext::class)->run();

        $this->assertNull($context['goal']);
    }

    public function test_goal_context_target_allocation_from_category_percentages(): void
    {
        $cat = Category::factory()->create(['name' => 'ETF']);
        $goal = Goal::create(['name' => 'G', 'target_value' => 100]);
        $goal->categoryAllocations()->create(['category_id' => $cat->id, 'percentage' => 60]);

        $context = app(BuildAdvisorContext::class)->run();

        $this->assertStringContainsString('ETF 60%', $context['goal']['target_allocation']);
    }

    public function test_goal_context_carries_the_milestones_sorted_by_value(): void
    {
        $goal = Goal::create(['name' => 'G', 'target_value' => 1000000, 'description' => 'Libertà']);
        $goal->milestones()->create(['target_value' => 500000, 'target_date' => '2041-01-01', 'notes' => 'Metà']);
        $goal->milestones()->create(['target_value' => 250000, 'target_date' => '2036-01-01', 'notes' => 'Primo quarto']);

        $context = app(BuildAdvisorContext::class)->run();

        $this->assertSame('Libertà', $context['goal']['description']);
        $this->assertCount(2, $context['goal']['milestones']);
        $this->assertSame(250000.0, $context['goal']['milestones'][0]['value']);
        $this->assertSame('2036', $context['goal']['milestones'][0]['year']);
        $this->assertSame('Primo quarto', $context['goal']['milestones'][0]['label']);
        $this->assertSame(500000.0, $context['goal']['milestones'][1]['value']);
    }
}
