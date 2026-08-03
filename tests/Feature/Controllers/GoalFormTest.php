<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Category;
use App\Models\Goal;
use App\Models\InvestorProfile;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GoalFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_stores_a_goal_with_per_milestone_allocations(): void
    {
        $azioni = Category::factory()->create(['name' => 'Azioni']);
        $liquidita = Category::factory()->create(['name' => 'Liquidità']);

        $this->post('/goal', [
            'name' => 'FIRE',
            'target_value' => 1000000,
            'milestones' => [
                ['notes' => 'Primo quarto', 'action' => 'Ribilancia il portafoglio.', 'rationale' => 'Mantiene il profilo di rischio.', 'target_value' => 250000, 'target_date' => '2036-01-01', 'allocation' => [
                    ['category_id' => $azioni->id, 'percentage' => 70],
                    ['category_id' => $liquidita->id, 'percentage' => 30],
                ]],
            ],
        ])->assertRedirect();

        $goal = Goal::query()->firstOrFail();
        $milestone = $goal->milestones()->firstOrFail();
        $this->assertSame('Ribilancia il portafoglio.', $milestone->action);
        $this->assertSame('Mantiene il profilo di rischio.', $milestone->rationale);
        $this->assertSame(2, $milestone->categoryAllocations()->count());
        $this->assertDatabaseHas('goal_category_allocations', [
            'milestone_id' => $milestone->id, 'category_id' => $azioni->id, 'percentage' => 70.0,
        ]);
        // No global (milestone_id null) allocation is created by the form anymore.
        $this->assertSame(0, $goal->categoryAllocations()->whereNull('milestone_id')->count());
    }

    public function test_updating_replaces_milestones_and_their_allocations(): void
    {
        $azioni = Category::factory()->create(['name' => 'Azioni']);
        $goal = Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2050-01-01']);
        $old = $goal->milestones()->create(['target_value' => 100000, 'target_date' => '2030-01-01']);
        $old->categoryAllocations()->create(['goal_id' => $goal->id, 'category_id' => $azioni->id, 'percentage' => 50]);

        $this->put("/goal/{$goal->id}", [
            'name' => 'G',
            'target_value' => 1000000,
            'milestones' => [
                ['target_value' => 250000, 'target_date' => '2036-01-01', 'allocation' => [
                    ['category_id' => $azioni->id, 'percentage' => 80],
                ]],
            ],
        ])->assertRedirect();

        $this->assertSame(1, $goal->milestones()->count());
        // The old milestone's allocation is gone; the new one's is present.
        $this->assertDatabaseMissing('goal_category_allocations', ['percentage' => 50.0, 'deleted_at' => null]);
        $this->assertDatabaseHas('goal_category_allocations', ['category_id' => $azioni->id, 'percentage' => 80.0, 'deleted_at' => null]);
    }

    public function test_page_carries_the_investor_profile_with_the_derived_horizon(): void
    {
        // Profile and goal describe one thing, so the Goal page owns the profile
        // card — and its horizon comes from the goal's target date.
        InvestorProfile::create(['name' => 'Mario', 'risk_tolerance' => 'high']);
        Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => now()->addYears(20)->format('Y-m-d')]);

        $this->get('/goal')->assertInertia(fn (Assert $page) => $page
            ->where('profile.name', 'Mario')
            ->where('profile.risk_tolerance', 'high')
            ->where('profile.horizon', 'long'),
        );
    }

    public function test_page_profile_is_null_when_no_profile_exists(): void
    {
        $this->get('/goal')->assertInertia(fn (Assert $page) => $page->where('profile', null));
    }
}
