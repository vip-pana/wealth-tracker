<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Models\Category;
use App\Models\Goal;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreGoalFromAdvisorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_goal_core_creates_the_first_goal_when_none_exists(): void
    {
        $this->post('/advisor/goal', [
            'target_value' => 1000000,
            'target_date' => '2099-12-31',
            'description' => 'Primo milione',
        ])->assertRedirect();

        $this->assertDatabaseHas('goal', [
            'description' => 'Primo milione',
            'target_value' => 1000000.0,
            'target_date' => '2099-12-31',
        ]);
    }

    public function test_goal_core_updates_the_existing_goal_in_place(): void
    {
        $goal = Goal::create(['name' => 'Vecchio', 'target_value' => 500000, 'target_date' => '2090-01-01']);

        $this->post('/advisor/goal', ['target_value' => 750000])->assertRedirect();

        $this->assertSame(1, Goal::query()->count());
        $this->assertSame(750000.0, (float) $goal->fresh()?->target_value);
    }

    public function test_goal_core_defaults_a_name_from_the_description_when_creating(): void
    {
        $this->post('/advisor/goal', ['target_value' => 1000000, 'description' => 'Libertà finanziaria'])
            ->assertRedirect();

        $this->assertDatabaseHas('goal', ['name' => 'Libertà finanziaria']);
    }

    public function test_milestones_replace_only_milestones_leaving_composition_intact(): void
    {
        $goal = Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2099-01-01']);
        $goal->categoryAllocations()->create(['category_id' => null, 'macro_category' => 'ETF', 'percentage' => 80]);
        $goal->milestones()->create(['notes' => 'Vecchia', 'target_value' => 100000, 'target_date' => '2050-01-01']);

        $this->post('/advisor/goal/milestones', [
            'milestones' => [
                ['notes' => 'Metà', 'action' => 'Sposta 5% da Bitcoin a Obbligazioni.', 'rationale' => 'Riduce la volatilità.', 'target_value' => 500000, 'target_date' => '2080-01-01'],
            ],
        ])->assertRedirect();

        $this->assertSame(1, $goal->milestones()->count());
        $this->assertDatabaseHas('goal_milestones', ['notes' => 'Metà', 'action' => 'Sposta 5% da Bitcoin a Obbligazioni.', 'rationale' => 'Riduce la volatilità.', 'target_value' => 500000.0, 'deleted_at' => null]);
        // The old milestone is gone from the active set (soft-deleted).
        $this->assertDatabaseMissing('goal_milestones', ['notes' => 'Vecchia', 'deleted_at' => null]);
        // The global composition (milestone_id null) is untouched.
        $this->assertSame(1, $goal->categoryAllocations()->whereNull('milestone_id')->count());
    }

    public function test_milestones_persist_their_own_target_allocation(): void
    {
        $azioni = Category::factory()->create(['name' => 'Azioni']);
        $liquidita = Category::factory()->create(['name' => 'Liquidità']);
        $goal = Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2099-01-01']);

        $this->post('/advisor/goal/milestones', [
            'milestones' => [
                ['notes' => 'Metà', 'target_value' => 500000, 'target_date' => '2080-01-01', 'allocation' => [
                    ['category' => 'Azioni', 'percentage' => 70],
                    ['category' => 'Liquidità', 'percentage' => 30],
                ]],
            ],
        ])->assertRedirect();

        $milestone = $goal->milestones()->firstOrFail();
        $this->assertSame(2, $milestone->categoryAllocations()->count());
        $this->assertDatabaseHas('goal_category_allocations', [
            'milestone_id' => $milestone->id, 'category_id' => $azioni->id, 'percentage' => 70.0,
        ]);
        $this->assertDatabaseHas('goal_category_allocations', [
            'milestone_id' => $milestone->id, 'category_id' => $liquidita->id, 'percentage' => 30.0,
        ]);
    }

    public function test_milestones_persist_a_per_category_cap_amount(): void
    {
        Category::factory()->create(['name' => 'Azioni']);
        $liquidita = Category::factory()->create(['name' => 'Liquidità']);
        $goal = Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2099-01-01']);

        $this->post('/advisor/goal/milestones', [
            'milestones' => [
                ['notes' => 'Metà', 'target_value' => 500000, 'target_date' => '2080-01-01', 'allocation' => [
                    ['category' => 'Azioni', 'percentage' => 85],
                    ['category' => 'Liquidità', 'percentage' => 15, 'cap_amount' => 50000],
                ]],
            ],
        ])->assertRedirect();

        $milestone = $goal->milestones()->firstOrFail();
        // The capped row stores its cap; the uncapped one stays null.
        $this->assertDatabaseHas('goal_category_allocations', [
            'milestone_id' => $milestone->id, 'category_id' => $liquidita->id, 'percentage' => 15.0, 'cap_amount' => 50000.0,
        ]);
        $this->assertNull($milestone->categoryAllocations()->whereNull('cap_amount')->firstOrFail()->cap_amount);
    }

    public function test_composition_replaces_only_allocations_leaving_milestones_intact(): void
    {
        $azioni = Category::factory()->create(['name' => 'Azioni']);
        $liquidita = Category::factory()->create(['name' => 'Liquidità']);
        $goal = Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2099-01-01']);
        $goal->categoryAllocations()->create(['category_id' => $azioni->id, 'macro_category' => null, 'percentage' => 100]);
        $goal->milestones()->create(['notes' => 'Tappa', 'target_value' => 100000, 'target_date' => '2050-01-01']);

        $this->post('/advisor/goal/composition', [
            'allocations' => [
                ['category' => 'Azioni', 'percentage' => 70],
                ['category' => 'Liquidità', 'percentage' => 30],
            ],
        ])->assertRedirect();

        $this->assertSame(2, $goal->categoryAllocations()->count());
        $this->assertDatabaseHas('goal_category_allocations', ['category_id' => $azioni->id, 'percentage' => 70.0, 'deleted_at' => null]);
        $this->assertDatabaseHas('goal_category_allocations', ['category_id' => $liquidita->id, 'percentage' => 30.0, 'deleted_at' => null]);
        // Milestones untouched.
        $this->assertSame(1, $goal->milestones()->count());
    }

    public function test_composition_rejects_an_unknown_category(): void
    {
        Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2099-01-01']);

        $this->post('/advisor/goal/composition', [
            'allocations' => [['category' => 'Categoria Inesistente', 'percentage' => 100]],
        ])->assertSessionHasErrors('allocations.0.category');
    }

    public function test_milestones_without_a_goal_redirect_with_an_error(): void
    {
        $this->post('/advisor/goal/milestones', [
            'milestones' => [['target_value' => 100, 'target_date' => '2099-01-01']],
        ])->assertRedirect();

        $this->assertSame(0, Goal::query()->count());
    }
}
