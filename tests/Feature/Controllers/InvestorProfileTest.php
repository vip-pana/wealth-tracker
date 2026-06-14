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
            'objective' => 'Indipendenza finanziaria',
            'target_allocation' => '80% azioni',
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
        InvestorProfile::create(['horizon' => 'long', 'risk_tolerance' => 'high', 'objective' => 'Pensione']);

        $context = app(BuildAdvisorContext::class)->run();

        $this->assertNotNull($context['investorProfile']);
        $this->assertSame('long', $context['investorProfile']['horizon']);
        $this->assertSame('Pensione', $context['investorProfile']['objective']['value']);
        $this->assertSame('profile', $context['investorProfile']['objective']['source']);
    }

    public function test_context_profile_is_null_when_unset(): void
    {
        $context = app(BuildAdvisorContext::class)->run();

        $this->assertNull($context['investorProfile']);
    }

    public function test_objective_defaults_to_goal_when_profile_empty(): void
    {
        InvestorProfile::create(['horizon' => 'long']); // no objective override
        Goal::create(['name' => 'Il primo milione', 'target_value' => 1000000, 'target_date' => '2035-01-01']);

        $context = app(BuildAdvisorContext::class)->run();

        $this->assertSame('goal', $context['investorProfile']['objective']['source']);
        $this->assertStringContainsString('Il primo milione', $context['investorProfile']['objective']['value']);
    }

    public function test_profile_objective_overrides_goal(): void
    {
        InvestorProfile::create(['objective' => 'Comprare casa']);
        Goal::create(['name' => 'Il primo milione', 'target_value' => 1000000]);

        $context = app(BuildAdvisorContext::class)->run();

        $this->assertSame('profile', $context['investorProfile']['objective']['source']);
        $this->assertSame('Comprare casa', $context['investorProfile']['objective']['value']);
    }

    public function test_allocation_defaults_to_goal_category_percentages(): void
    {
        $cat = Category::factory()->create(['name' => 'ETF']);
        $goal = Goal::create(['name' => 'G', 'target_value' => 100]);
        $goal->categoryAllocations()->create(['category_id' => $cat->id, 'percentage' => 60]);

        $context = app(BuildAdvisorContext::class)->run();

        $this->assertSame('goal', $context['investorProfile']['target_allocation']['source']);
        $this->assertStringContainsString('ETF 60%', $context['investorProfile']['target_allocation']['value']);
    }
}
