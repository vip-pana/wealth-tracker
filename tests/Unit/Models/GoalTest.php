<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Goal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a goal with one milestone at $target and the given per-category
     * allocation (name => [pct, cap]). Returns the goal with relations loaded.
     *
     * @param  array<string, array{0: float, 1: float|null}>  $allocation
     */
    private function goalWithMilestone(float $target, array $allocation): Goal
    {
        $goal = Goal::create(['name' => 'G', 'target_value' => $target, 'target_date' => '2099-01-01']);
        $milestone = $goal->milestones()->create(['target_value' => $target, 'target_date' => '2099-01-01']);

        foreach ($allocation as $name => [$pct, $cap]) {
            $category = Category::factory()->create(['name' => $name]);
            $milestone->categoryAllocations()->create([
                'goal_id' => $goal->id,
                'category_id' => $category->id,
                'percentage' => $pct,
                'cap_amount' => $cap,
            ]);
        }

        return $goal->load(['milestones.categoryAllocations', 'categoryAllocations']);
    }

    public function test_current_target_allocation_is_unchanged_when_no_cap_binds(): void
    {
        $goal = $this->goalWithMilestone(250_000, [
            'Azioni' => [50.0, null],
            'Liquidità' => [15.0, 50_000.0], // 15% of 250k = 37.5k ≤ 50k
            'Bitcoin' => [35.0, null],
        ]);

        $byName = $goal->currentTargetAllocation(0.0)->keyBy(fn ($a) => $a->category->name);

        $this->assertSame(50.0, $byName['Azioni']->percentage);
        $this->assertSame(15.0, $byName['Liquidità']->percentage);
        $this->assertSame(35.0, $byName['Bitcoin']->percentage);
    }

    public function test_current_target_allocation_clamps_a_binding_cap_and_spreads_pro_rata(): void
    {
        // At the 1M milestone, liquidity 15% = 150k > 50k cap → clamp to 5%,
        // spread the freed 10pp over Azioni(50)+Bitcoin(35) = 85pp.
        $goal = $this->goalWithMilestone(1_000_000, [
            'Azioni' => [50.0, null],
            'Liquidità' => [15.0, 50_000.0],
            'Bitcoin' => [35.0, null],
        ]);

        $byName = $goal->currentTargetAllocation(0.0)->keyBy(fn ($a) => $a->category->name);

        $this->assertEqualsWithDelta(5.0, $byName['Liquidità']->percentage, 0.001);
        $this->assertEqualsWithDelta(50.0 + (50.0 / 85.0) * 10.0, $byName['Azioni']->percentage, 0.001);
        $this->assertEqualsWithDelta(35.0 + (35.0 / 85.0) * 10.0, $byName['Bitcoin']->percentage, 0.001);

        $sum = $goal->currentTargetAllocation(0.0)->sum(fn ($a) => $a->percentage);
        $this->assertEqualsWithDelta(100.0, $sum, 0.001);
    }

    public function test_current_target_allocation_does_not_persist_the_capped_percentages(): void
    {
        $goal = $this->goalWithMilestone(1_000_000, [
            'Azioni' => [85.0, null],
            'Liquidità' => [15.0, 50_000.0],
        ]);

        // Resolve once (mutates in-memory), then reload from the DB.
        $goal->currentTargetAllocation(0.0);
        $fresh = $goal->fresh(['milestones.categoryAllocations']);
        $liq = $fresh?->milestones->first()?->categoryAllocations->firstWhere('percentage', 15.0);

        $this->assertNotNull($liq, 'The stored percentage must remain the raw 15, not the capped value.');
    }
}
