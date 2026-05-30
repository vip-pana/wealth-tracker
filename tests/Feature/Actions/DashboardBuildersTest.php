<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Dashboard\BuildMacroAllocationData;
use App\Actions\Dashboard\BuildMacroStackedBar;
use App\Actions\Dashboard\ComputeMonthComparison;
use App\Enums\MacroCategory;
use App\Models\Category;
use App\Models\Snapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DashboardBuildersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, float>  $values  category_id => value
     */
    private function snapshot(string $date, array $values): Snapshot
    {
        $snapshot = Snapshot::create(['date' => $date, 'total_value' => array_sum($values)]);
        foreach ($values as $categoryId => $value) {
            SnapshotCategoryValue::create([
                'snapshot_id' => $snapshot->id,
                'category_id' => $categoryId,
                'value' => $value,
            ]);
        }

        return $snapshot;
    }

    /** @return Collection<int, Snapshot> */
    private function loadSnapshots(): Collection
    {
        return Snapshot::with('categoryValues.category')->orderBy('date')->get();
    }

    public function test_month_comparison_returns_empty_with_fewer_than_two_snapshots(): void
    {
        $cat = Category::factory()->create();
        $this->snapshot('2026-01-01', [$cat->id => 100]);

        $result = app(ComputeMonthComparison::class)->run($this->loadSnapshots(), Category::all());

        $this->assertSame([], $result);
    }

    public function test_month_comparison_uses_last_two_snapshots_in_order(): void
    {
        $cat = Category::factory()->create(['name' => 'ETF']);
        $this->snapshot('2026-01-01', [$cat->id => 100]);
        $this->snapshot('2026-02-01', [$cat->id => 150]);
        $this->snapshot('2026-03-01', [$cat->id => 200]);

        $result = app(ComputeMonthComparison::class)->run($this->loadSnapshots(), Category::all());

        // current = latest (Mar, 200), previous = penultimate (Feb, 150).
        $this->assertSame('ETF', $result[0]['category']);
        $this->assertEqualsWithDelta(200.0, $result[0]['current'], 0.01);
        $this->assertEqualsWithDelta(150.0, $result[0]['previous'], 0.01);
    }

    public function test_month_comparison_defaults_missing_category_to_zero(): void
    {
        $a = Category::factory()->create(['name' => 'A']);
        $b = Category::factory()->create(['name' => 'B']);
        $this->snapshot('2026-01-01', [$a->id => 100]);
        $this->snapshot('2026-02-01', [$a->id => 120, $b->id => 50]);

        $result = app(ComputeMonthComparison::class)->run($this->loadSnapshots(), Category::all());

        $byName = collect($result)->keyBy('category');
        // B is only in the latest snapshot -> previous should be 0.
        $this->assertEqualsWithDelta(50.0, $byName['B']['current'], 0.01);
        $this->assertEqualsWithDelta(0.0, $byName['B']['previous'], 0.01);
    }

    public function test_macro_allocation_sums_categories_sharing_a_macro(): void
    {
        $etf1 = Category::factory()->create(['macro_category' => MacroCategory::ETF]);
        $etf2 = Category::factory()->create(['macro_category' => MacroCategory::ETF]);
        $cripto = Category::factory()->create(['macro_category' => MacroCategory::Cripto]);
        $this->snapshot('2026-01-01', [$etf1->id => 100, $etf2->id => 200, $cripto->id => 50]);

        $result = app(BuildMacroAllocationData::class)->run($this->loadSnapshots());

        $byName = collect($result)->keyBy('name');
        $this->assertEqualsWithDelta(300.0, $byName['ETF']['value'], 0.01);
        $this->assertEqualsWithDelta(50.0, $byName['Cripto']['value'], 0.01);
    }

    public function test_macro_allocation_skips_categories_without_a_macro(): void
    {
        $etf = Category::factory()->create(['macro_category' => MacroCategory::ETF]);
        $none = Category::factory()->create(['macro_category' => null]);
        $this->snapshot('2026-01-01', [$etf->id => 100, $none->id => 999]);

        $result = app(BuildMacroAllocationData::class)->run($this->loadSnapshots());

        $names = collect($result)->pluck('name')->all();
        $this->assertContains('ETF', $names);
        $this->assertCount(1, $result);
    }

    public function test_macro_allocation_empty_without_snapshots(): void
    {
        $this->assertSame([], app(BuildMacroAllocationData::class)->run($this->loadSnapshots()));
    }

    public function test_macro_stacked_bar_groups_by_macro_per_snapshot(): void
    {
        $etf1 = Category::factory()->create(['macro_category' => MacroCategory::ETF]);
        $etf2 = Category::factory()->create(['macro_category' => MacroCategory::ETF]);
        $this->snapshot('2026-01-01', [$etf1->id => 100, $etf2->id => 200]);
        $this->snapshot('2026-02-01', [$etf1->id => 150, $etf2->id => 250]);

        $result = app(BuildMacroStackedBar::class)->run($this->loadSnapshots());

        $this->assertSame('2026-01-01', $result[0]['date']);
        $this->assertEqualsWithDelta(300.0, $result[0]['ETF'], 0.01);
        $this->assertSame('2026-02-01', $result[1]['date']);
        $this->assertEqualsWithDelta(400.0, $result[1]['ETF'], 0.01);
    }
}
