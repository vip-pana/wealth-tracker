<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Snapshots\BuildSnapshotDiff;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Snapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildSnapshotDiffTest extends TestCase
{
    use RefreshDatabase;

    private function snapshot(string $date, array $byCategory): Snapshot
    {
        $snapshot = Snapshot::create(['date' => $date, 'total_value' => array_sum($byCategory)]);

        foreach ($byCategory as $categoryId => $value) {
            SnapshotCategoryValue::create([
                'snapshot_id' => $snapshot->id,
                'category_id' => $categoryId,
                'value' => $value,
            ]);
        }

        return $snapshot;
    }

    public function test_returns_null_without_a_snapshot_to_compare_against(): void
    {
        $category = Category::factory()->create();
        Asset::factory()->create(['category_id' => $category->id, 'value' => 1000, 'date' => '2026-07-01']);

        $this->assertNull(app(BuildSnapshotDiff::class)->run('2026-07-15'));
    }

    public function test_reports_the_change_per_category(): void
    {
        $liquid = Category::factory()->create(['name' => 'Liquidità']);
        $gold = Category::factory()->create(['name' => 'Oro']);
        $this->snapshot('2026-07-01', [$liquid->id => 1000, $gold->id => 500]);

        Asset::factory()->create(['category_id' => $liquid->id, 'value' => 1200, 'date' => '2026-07-10']);
        Asset::factory()->create(['category_id' => $gold->id, 'value' => 500, 'date' => '2026-07-10']);

        $diff = app(BuildSnapshotDiff::class)->run('2026-07-15');

        $this->assertSame('2026-07-01', $diff['snapshotDate']);
        $rows = collect($diff['rows'])->keyBy('category');
        $this->assertEqualsWithDelta(200.0, $rows['Liquidità']['delta'], 0.01);
        $this->assertEqualsWithDelta(0.0, $rows['Oro']['delta'], 0.01);
        $this->assertEqualsWithDelta(1500.0, $diff['previousTotal'], 0.01);
        $this->assertEqualsWithDelta(1700.0, $diff['currentTotal'], 0.01);
    }

    public function test_orders_rows_by_how_much_they_moved(): void
    {
        $small = Category::factory()->create(['name' => 'Piccola']);
        $big = Category::factory()->create(['name' => 'Grande']);
        $this->snapshot('2026-07-01', [$small->id => 100, $big->id => 1000]);

        Asset::factory()->create(['category_id' => $small->id, 'value' => 110, 'date' => '2026-07-10']);
        Asset::factory()->create(['category_id' => $big->id, 'value' => 1500, 'date' => '2026-07-10']);

        $diff = app(BuildSnapshotDiff::class)->run('2026-07-15');

        $this->assertSame('Grande', $diff['rows'][0]['category']);
    }

    public function test_includes_a_category_that_only_one_side_knows_about(): void
    {
        // A category added since the snapshot has no previous value; one that was
        // emptied has no current one. Both are changes worth showing.
        $existing = Category::factory()->create(['name' => 'Esistente']);
        $fresh = Category::factory()->create(['name' => 'Nuova']);
        $this->snapshot('2026-07-01', [$existing->id => 1000]);

        Asset::factory()->create(['category_id' => $existing->id, 'value' => 1000, 'date' => '2026-07-10']);
        Asset::factory()->create(['category_id' => $fresh->id, 'value' => 300, 'date' => '2026-07-10']);

        $diff = app(BuildSnapshotDiff::class)->run('2026-07-15');

        $rows = collect($diff['rows'])->keyBy('category');
        $this->assertEqualsWithDelta(0.0, $rows['Nuova']['previous'], 0.01);
        $this->assertEqualsWithDelta(300.0, $rows['Nuova']['delta'], 0.01);
    }

    public function test_excludes_illiquid_categories(): void
    {
        // The snapshot stores them, but the Bilancio page never shows them, so
        // the diff would report a change the user cannot act on here.
        $liquid = Category::factory()->create(['name' => 'Liquidità']);
        $pension = Category::factory()->create(['name' => 'Pensione', 'macro_category' => 'Fondo Pensione']);
        $this->snapshot('2026-07-01', [$liquid->id => 1000, $pension->id => 5000]);

        Asset::factory()->create(['category_id' => $liquid->id, 'value' => 1000, 'date' => '2026-07-10']);
        Asset::factory()->create(['category_id' => $pension->id, 'value' => 6000, 'date' => '2026-07-10']);

        $diff = app(BuildSnapshotDiff::class)->run('2026-07-15');

        $this->assertSame(['Liquidità'], array_column($diff['rows'], 'category'));
        $this->assertEqualsWithDelta(1000.0, $diff['currentTotal'], 0.01);
    }

    public function test_compares_against_the_most_recent_snapshot(): void
    {
        $category = Category::factory()->create();
        $this->snapshot('2026-06-01', [$category->id => 100]);
        $this->snapshot('2026-07-01', [$category->id => 900]);

        Asset::factory()->create(['category_id' => $category->id, 'value' => 1000, 'date' => '2026-07-10']);

        $diff = app(BuildSnapshotDiff::class)->run('2026-07-15');

        $this->assertSame('2026-07-01', $diff['snapshotDate']);
        $this->assertEqualsWithDelta(100.0, $diff['rows'][0]['delta'], 0.01);
    }
}
