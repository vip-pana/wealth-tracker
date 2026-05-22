<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Snapshots\StoreSnapshot;
use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\Category;
use App\Models\MonthlySnapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSnapshotCarryForwardTest extends TestCase
{
    use RefreshDatabase;

    public function test_includes_pension_value_when_pension_asset_exists_in_target_month(): void
    {
        $etf = Category::factory()->create(['macro_category' => MacroCategory::ETF]);
        $pension = Category::factory()->create(['macro_category' => MacroCategory::FondoPensione]);

        Asset::factory()->create(['category_id' => $etf->id, 'value' => 10000, 'date' => '2026-01-31']);
        Asset::factory()->create(['category_id' => $pension->id, 'value' => 5000, 'date' => '2026-01-31']);

        app(StoreSnapshot::class)->run('2026-01-31');

        $this->assertDatabaseHas('monthly_snapshots', ['date' => '2026-01-31', 'total_value' => 15000.00]);
        $snapshot = MonthlySnapshot::firstWhere('date', '2026-01-31');
        $this->assertNotNull($snapshot);
        $this->assertEqualsWithDelta(5000.00, SnapshotCategoryValue::where('snapshot_id', $snapshot->id)->where('category_id', $pension->id)->value('value'), 0.01);
    }

    public function test_carries_pension_value_forward_to_later_months(): void
    {
        $etf = Category::factory()->create(['macro_category' => MacroCategory::ETF]);
        $pension = Category::factory()->create(['macro_category' => MacroCategory::FondoPensione]);

        Asset::factory()->create(['category_id' => $pension->id, 'value' => 5000, 'date' => '2026-01-31']);
        Asset::factory()->create(['category_id' => $etf->id, 'value' => 12000, 'date' => '2026-05-31']);

        app(StoreSnapshot::class)->run('2026-05-31');

        $this->assertDatabaseHas('monthly_snapshots', ['date' => '2026-05-31', 'total_value' => 17000.00]);
        $snapshot = MonthlySnapshot::firstWhere('date', '2026-05-31');
        $this->assertNotNull($snapshot);
        $this->assertEqualsWithDelta(5000.00, SnapshotCategoryValue::where('snapshot_id', $snapshot->id)->where('category_id', $pension->id)->value('value'), 0.01);
    }

    public function test_uses_latest_pension_value_when_multiple_reports_exist(): void
    {
        $pension = Category::factory()->create(['macro_category' => MacroCategory::FondoPensione]);

        Asset::factory()->create(['category_id' => $pension->id, 'value' => 5000, 'date' => '2025-12-31']);
        Asset::factory()->create(['category_id' => $pension->id, 'value' => 7000, 'date' => '2026-12-31']);

        app(StoreSnapshot::class)->run('2027-03-31');

        $this->assertDatabaseHas('monthly_snapshots', ['date' => '2027-03-31', 'total_value' => 7000.00]);
    }

    public function test_does_not_include_future_pension_reports(): void
    {
        $pension = Category::factory()->create(['macro_category' => MacroCategory::FondoPensione]);

        Asset::factory()->create(['category_id' => $pension->id, 'value' => 9999, 'date' => '2027-12-31']);

        app(StoreSnapshot::class)->run('2026-05-31');

        $this->assertDatabaseHas('monthly_snapshots', ['date' => '2026-05-31', 'total_value' => 0.00]);
    }

    public function test_sums_multiple_pension_assets_on_same_date(): void
    {
        $pension = Category::factory()->create(['macro_category' => MacroCategory::FondoPensione]);

        Asset::factory()->create(['category_id' => $pension->id, 'value' => 3000, 'date' => '2026-12-31']);
        Asset::factory()->create(['category_id' => $pension->id, 'value' => 2000, 'date' => '2026-12-31']);

        app(StoreSnapshot::class)->run('2027-06-30');

        $this->assertDatabaseHas('monthly_snapshots', ['date' => '2027-06-30', 'total_value' => 5000.00]);
    }

    public function test_pension_carry_forward_does_not_double_count_when_pension_asset_present_in_target_month(): void
    {
        $pension = Category::factory()->create(['macro_category' => MacroCategory::FondoPensione]);

        Asset::factory()->create(['category_id' => $pension->id, 'value' => 4000, 'date' => '2025-12-31']);
        Asset::factory()->create(['category_id' => $pension->id, 'value' => 6000, 'date' => '2026-12-31']);

        app(StoreSnapshot::class)->run('2026-12-31');

        $this->assertDatabaseHas('monthly_snapshots', ['date' => '2026-12-31', 'total_value' => 6000.00]);
    }
}
