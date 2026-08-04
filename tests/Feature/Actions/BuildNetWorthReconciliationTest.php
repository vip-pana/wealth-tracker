<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Snapshots\BuildNetWorthReconciliation;
use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildNetWorthReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_splits_current_month_from_carried_forward_categories(): void
    {
        $stocks = Category::factory()->create(['name' => 'Azioni']);
        $gold = Category::factory()->create(['name' => 'Oro']);

        Asset::factory()->create(['category_id' => $stocks->id, 'value' => 16000, 'date' => '2026-07-01']);
        // No July row: carried forward from December.
        Asset::factory()->create(['category_id' => $gold->id, 'value' => 1260, 'date' => '2025-12-31']);

        $result = app(BuildNetWorthReconciliation::class)->run('2026-07-15', '2026-07');

        $this->assertEqualsWithDelta(17260.0, $result['total'], 0.01);
        $this->assertEqualsWithDelta(16000.0, $result['currentMonthTotal'], 0.01);
        $this->assertEqualsWithDelta(1260.0, $result['carriedForwardTotal'], 0.01);
        $this->assertCount(1, $result['carriedForward']);
        $this->assertSame('Oro', $result['carriedForward'][0]['category']);
        $this->assertSame('2025-12-31', $result['carriedForward'][0]['asOf']);
    }

    public function test_illiquid_categories_are_excluded_from_rows_and_total(): void
    {
        $stocks = Category::factory()->create(['name' => 'Azioni']);
        $pension = Category::factory()->create([
            'name' => 'Fondo Pensione',
            'macro_category' => MacroCategory::FondoPensione,
        ]);

        Asset::factory()->create(['category_id' => $stocks->id, 'value' => 16000, 'date' => '2026-07-01']);
        // Carried forward from December, but illiquid: must not surface at all.
        Asset::factory()->create(['category_id' => $pension->id, 'value' => 1260, 'date' => '2025-12-31']);

        $result = app(BuildNetWorthReconciliation::class)->run('2026-07-15', '2026-07');

        $this->assertEqualsWithDelta(16000.0, $result['total'], 0.01);
        $this->assertEqualsWithDelta(16000.0, $result['currentMonthTotal'], 0.01);
        $this->assertEqualsWithDelta(0.0, $result['carriedForwardTotal'], 0.01);
        $this->assertSame([], $result['carriedForward']);
    }

    public function test_no_carried_forward_when_every_category_has_a_current_month_row(): void
    {
        $stocks = Category::factory()->create(['name' => 'Azioni']);
        Asset::factory()->create(['category_id' => $stocks->id, 'value' => 5000, 'date' => '2026-07-01']);

        $result = app(BuildNetWorthReconciliation::class)->run('2026-07-15', '2026-07');

        $this->assertEqualsWithDelta(5000.0, $result['currentMonthTotal'], 0.01);
        $this->assertSame([], $result['carriedForward']);
        $this->assertEqualsWithDelta(0.0, $result['carriedForwardTotal'], 0.01);
    }
}
