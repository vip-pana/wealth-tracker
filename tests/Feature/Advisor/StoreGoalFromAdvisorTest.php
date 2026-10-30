<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Actions\Goals\FetchGoalData;
use App\Advisor\AdvisorOutputFilter;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Goal;
use App\Models\InvestorProfile;
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
                ['notes' => 'Metà', 'action' => 'Da qui il peso della parte azionaria scende e quello della parte obbligazionaria sale.', 'rationale' => 'Riduce la volatilità.', 'target_value' => 500000, 'target_date' => '2080-01-01'],
            ],
        ])->assertRedirect();

        $this->assertSame(1, $goal->milestones()->count());
        $this->assertDatabaseHas('goal_milestones', ['notes' => 'Metà', 'action' => 'Da qui il peso della parte azionaria scende e quello della parte obbligazionaria sale.', 'rationale' => 'Riduce la volatilità.', 'target_value' => 500000.0, 'deleted_at' => null]);
        // The old milestone is gone from the active set (soft-deleted).
        $this->assertDatabaseMissing('goal_milestones', ['notes' => 'Vecchia', 'deleted_at' => null]);
        // The global composition (milestone_id null) is untouched.
        $this->assertSame(1, $goal->categoryAllocations()->whereNull('milestone_id')->count());
    }

    /**
     * F1 of the WEA-10 review: a milestone's `action` is free text the model
     * wrote, it was posted straight into `goal_milestones`, and the Goal page
     * then showed it for good — outside the advisor, with no filter and no
     * footer. It goes through the firewall on the way in now.
     */
    public function test_an_operative_instruction_in_a_milestone_is_redacted_before_it_is_stored(): void
    {
        Asset::factory()->create(['name' => 'Bitcoin']);
        Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2099-01-01']);

        $this->post('/advisor/goal/milestones', [
            'milestones' => [
                [
                    'notes' => 'Metà',
                    'action' => 'Sposta il 5% del portafoglio da Bitcoin a Obbligazioni e rivedi il PAC.',
                    'rationale' => 'Riduce la volatilità avvicinandosi al target.',
                    'target_value' => 500000,
                    'target_date' => '2080-01-01',
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseMissing('goal_milestones', ['action' => 'Sposta il 5% del portafoglio da Bitcoin a Obbligazioni e rivedi il PAC.']);
        $this->assertDatabaseHas('goal_milestones', ['action' => AdvisorOutputFilter::REDACTION]);
        // The reasoning around it is kept: only the instruction goes.
        $this->assertDatabaseHas('goal_milestones', ['rationale' => 'Riduce la volatilità avvicinandosi al target.']);
    }

    /**
     * The three fields that sit beside the milestones and were not covered in
     * either direction — `goals.description`, `investor_profiles.notes` and
     * `investor_profiles.memory`. Each is written by the advisor, kept for
     * good, and shown on a page outside the chat, which is the whole reason
     * the milestone fields are screened; the rule was written down and then
     * not applied to the fields that already existed.
     */
    public function test_an_operative_instruction_in_the_goal_description_is_redacted_before_it_is_stored(): void
    {
        Asset::factory()->create(['name' => 'Bitcoin']);

        $this->post('/advisor/goal', [
            'target_value' => 1000000,
            'target_date' => '2099-12-31',
            'description' => 'Vendi Bitcoin e compra obbligazioni.',
        ])->assertRedirect();

        $this->assertDatabaseMissing('goal', ['description' => 'Vendi Bitcoin e compra obbligazioni.']);
        $this->assertDatabaseHas('goal', ['description' => AdvisorOutputFilter::REDACTION]);
    }

    /**
     * The name is derived from the description when the advisor proposes none,
     * so screening has to happen before the derivation or the removed text
     * walks into the other column.
     */
    public function test_the_name_derived_from_a_description_is_screened_too(): void
    {
        Asset::factory()->create(['name' => 'Bitcoin']);

        $this->post('/advisor/goal', [
            'target_value' => 1000000,
            'description' => 'Vendi Bitcoin e compra obbligazioni.',
        ])->assertRedirect();

        $this->assertDatabaseHas('goal', ['name' => AdvisorOutputFilter::REDACTION]);
    }

    public function test_a_goal_description_stored_before_the_filter_is_screened_on_the_way_out(): void
    {
        Asset::factory()->create(['name' => 'Bitcoin']);
        Goal::create([
            'name' => 'Vendi Bitcoin subito.',
            'description' => 'Vendi Bitcoin e compra obbligazioni.',
            'target_value' => 1000000,
            'target_date' => '2099-01-01',
        ]);

        $data = app(FetchGoalData::class)->run();

        $this->assertIsArray($data['goal']);
        $this->assertSame(AdvisorOutputFilter::REDACTION, $data['goal']['description']);
        $this->assertSame(AdvisorOutputFilter::REDACTION, $data['goal']['name']);
    }

    public function test_an_operative_instruction_in_the_profile_notes_is_redacted_before_it_is_stored(): void
    {
        Asset::factory()->create(['name' => 'Bitcoin']);

        $this->post('/advisor/profile', [
            'risk_tolerance' => 'medium',
            'notes' => 'Vendi Bitcoin e compra obbligazioni.',
        ])->assertRedirect();

        $this->assertDatabaseMissing('investor_profile', ['notes' => 'Vendi Bitcoin e compra obbligazioni.']);
        $this->assertDatabaseHas('investor_profile', ['notes' => AdvisorOutputFilter::REDACTION]);
    }

    public function test_profile_notes_and_memory_stored_before_the_filter_are_screened_on_the_way_out(): void
    {
        Asset::factory()->create(['name' => 'Bitcoin']);
        InvestorProfile::create([
            'risk_tolerance' => 'medium',
            'notes' => 'Vendi Bitcoin e compra obbligazioni.',
            'memory' => 'Vendi Bitcoin e compra obbligazioni.',
        ]);

        $data = app(FetchGoalData::class)->run();

        $this->assertIsArray($data['profile']);
        $this->assertSame(AdvisorOutputFilter::REDACTION, $data['profile']['notes']);
        $this->assertSame(AdvisorOutputFilter::REDACTION, $data['profile']['memory']);
    }

    /** The same field, read back: rows stored before the filter covered them. */
    public function test_a_milestone_stored_before_the_filter_is_screened_on_the_way_out(): void
    {
        Asset::factory()->create(['name' => 'Bitcoin']);
        $goal = Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2099-01-01']);
        $goal->milestones()->create([
            'notes' => 'Metà',
            'action' => 'Vendi Bitcoin e compra obbligazioni.',
            'target_value' => 500000,
            'target_date' => '2080-01-01',
        ]);

        $data = app(FetchGoalData::class)->run();

        $milestones = $data['goal']['milestones'];
        $this->assertIsArray($milestones);
        $this->assertSame(AdvisorOutputFilter::REDACTION, $milestones[0]['action']);
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

    // ---- The numeric channel: a target weight on a named instrument ----

    /**
     * `notes` / `action` / `rationale` go through the filter; the allocation is
     * percentages against category names and no lexical check can reach it. A
     * weight on a category the user named after an instrument is the
     * proposition the filter removes from the text, persisted as a row.
     */
    public function test_milestone_allocation_weighting_a_named_instrument_is_refused(): void
    {
        Category::factory()->create(['name' => 'Azionario']);
        Category::factory()->create(['name' => 'Bitcoin']);
        $goal = Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2099-01-01']);

        $this->post('/advisor/goal/milestones', [
            'milestones' => [
                ['notes' => 'Metà', 'target_value' => 500000, 'target_date' => '2080-01-01', 'allocation' => [
                    ['category' => 'Azionario', 'percentage' => 95],
                    ['category' => 'Bitcoin', 'percentage' => 5],
                ]],
            ],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, $goal->milestones()->count());
        $this->assertSame(0, $goal->categoryAllocations()->count());
    }

    /**
     * The write replaces every milestone, so the refusal has to come BEFORE the
     * delete: otherwise a rejected submission costs the user the plan they
     * already had and leaves them with nothing.
     */
    public function test_a_refused_milestone_write_leaves_the_existing_plan_intact(): void
    {
        $azioni = Category::factory()->create(['name' => 'Azionario']);
        Category::factory()->create(['name' => 'Bitcoin']);
        $goal = Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2099-01-01']);
        $existing = $goal->milestones()->create(['notes' => 'Tappa', 'target_value' => 100000, 'target_date' => '2050-01-01']);
        $goal->categoryAllocations()->create([
            'milestone_id' => $existing->id, 'category_id' => $azioni->id, 'macro_category' => null, 'percentage' => 100,
        ]);

        $this->post('/advisor/goal/milestones', [
            'milestones' => [
                ['target_value' => 500000, 'target_date' => '2080-01-01', 'allocation' => [
                    ['category' => 'Bitcoin', 'percentage' => 100],
                ]],
            ],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(1, $goal->milestones()->count());
        $this->assertSame(1, $existing->categoryAllocations()->count());
    }

    /** The category named after one of the user's own positions, not after a known instrument. */
    public function test_milestone_allocation_named_after_a_held_position_is_refused(): void
    {
        Asset::factory()->create(['name' => 'Vanguard FTSE All-World', 'ticker' => 'VWCE']);
        Category::factory()->create(['name' => 'Azionario']);
        Category::factory()->create(['name' => 'VWCE']);
        $goal = Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2099-01-01']);

        $this->post('/advisor/goal/milestones', [
            'milestones' => [
                ['target_value' => 500000, 'target_date' => '2080-01-01', 'allocation' => [
                    ['category' => 'Azionario', 'percentage' => 60],
                    ['category' => 'VWCE', 'percentage' => 40],
                ]],
            ],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, $goal->milestones()->count());
    }

    public function test_composition_weighting_a_named_instrument_is_refused_without_losing_the_current_one(): void
    {
        $azioni = Category::factory()->create(['name' => 'Azionario']);
        Category::factory()->create(['name' => 'Bitcoin']);
        $goal = Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2099-01-01']);
        $goal->categoryAllocations()->create(['category_id' => $azioni->id, 'macro_category' => null, 'percentage' => 100]);

        $this->post('/advisor/goal/composition', [
            'allocations' => [
                ['category' => 'Azionario', 'percentage' => 95],
                ['category' => 'Bitcoin', 'percentage' => 5],
            ],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(1, $goal->categoryAllocations()->count());
        $this->assertDatabaseHas('goal_category_allocations', ['category_id' => $azioni->id, 'percentage' => 100.0]);
    }

    /**
     * Read as well as write: rows saved before this gate existed are already in
     * the database, and the Goal page redraws them as a glide path for as long
     * as the goal lives. All-or-nothing, because the weights sum to 100.
     */
    public function test_a_stored_allocation_weighting_an_instrument_is_not_drawn(): void
    {
        $azioni = Category::factory()->create(['name' => 'Azionario']);
        $bitcoin = Category::factory()->create(['name' => 'Bitcoin']);
        $goal = Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2099-01-01']);
        $milestone = $goal->milestones()->create(['notes' => 'Tappa', 'target_value' => 500000, 'target_date' => '2080-01-01']);

        foreach ([[$azioni->id, 95.0], [$bitcoin->id, 5.0]] as [$categoryId, $pct]) {
            $goal->categoryAllocations()->create([
                'milestone_id' => $milestone->id, 'category_id' => $categoryId, 'macro_category' => null, 'percentage' => $pct,
            ]);
        }

        $data = app(FetchGoalData::class)->run();

        $this->assertSame([], $data['goal']['milestones'][0]['allocation']);
        $this->assertSame([], $data['goal']['categoryAllocations']);
        // The milestone itself survives — only its target composition is withheld.
        $this->assertSame(500000.0, (float) $data['goal']['milestones'][0]['target_value']);
    }

    public function test_a_stored_allocation_by_asset_class_is_drawn_unchanged(): void
    {
        $azioni = Category::factory()->create(['name' => 'Azionario']);
        $liquidita = Category::factory()->create(['name' => 'Liquidità']);
        $goal = Goal::create(['name' => 'G', 'target_value' => 1000000, 'target_date' => '2099-01-01']);
        $milestone = $goal->milestones()->create(['notes' => 'Tappa', 'target_value' => 500000, 'target_date' => '2080-01-01']);

        foreach ([[$azioni->id, 70.0], [$liquidita->id, 30.0]] as [$categoryId, $pct]) {
            $goal->categoryAllocations()->create([
                'milestone_id' => $milestone->id, 'category_id' => $categoryId, 'macro_category' => null, 'percentage' => $pct,
            ]);
        }

        $data = app(FetchGoalData::class)->run();

        $this->assertCount(2, $data['goal']['milestones'][0]['allocation']);
        $this->assertCount(2, $data['goal']['categoryAllocations']);
    }
}
