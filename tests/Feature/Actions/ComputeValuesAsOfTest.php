<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Snapshots\ComputeValuesAsOf;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ComputeValuesAsOfTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_the_latest_known_value_on_or_before_the_date(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create(['category_id' => $cat->id, 'value' => 1000, 'date' => '2026-01-01']);
        Asset::factory()->create(['category_id' => $cat->id, 'value' => 1500, 'date' => '2026-03-01']);

        $result = app(ComputeValuesAsOf::class)->run('2026-04-15');

        $this->assertEqualsWithDelta(1500.0, $result['total'], 0.01);
        $this->assertEqualsWithDelta(1500.0, $result['byCategory'][$cat->id], 0.01);
    }

    public function test_ignores_assets_dated_after_the_target(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create(['category_id' => $cat->id, 'value' => 1000, 'date' => '2026-01-01']);
        Asset::factory()->create(['category_id' => $cat->id, 'value' => 9999, 'date' => '2026-12-01']);

        $result = app(ComputeValuesAsOf::class)->run('2026-06-01');

        $this->assertEqualsWithDelta(1000.0, $result['total'], 0.01);
    }

    public function test_sums_multiple_assets_on_the_same_latest_date(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create(['category_id' => $cat->id, 'value' => 1000, 'date' => '2026-02-01']);
        Asset::factory()->create(['category_id' => $cat->id, 'value' => 250, 'date' => '2026-02-01']);

        $result = app(ComputeValuesAsOf::class)->run('2026-02-15');

        $this->assertEqualsWithDelta(1250.0, $result['byCategory'][$cat->id], 0.01);
    }

    public function test_prices_ticker_assets_live(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create([
            'category_id' => $cat->id,
            'ticker' => 'BTC',
            'quantity' => 0.5,
            'value' => 1, // stale stored value, should be ignored when priced live
            'date' => '2026-02-01',
        ]);
        AssetPrice::create(['ticker' => 'BTC', 'price' => 60000, 'currency' => 'EUR', 'fetched_at' => Carbon::parse('2026-02-10')]);

        $result = app(ComputeValuesAsOf::class)->run('2026-02-15');

        // 0.5 * 60000 = 30000, not the stored value of 1.
        $this->assertEqualsWithDelta(30000.0, $result['byCategory'][$cat->id], 0.01);
    }

    public function test_ticker_without_a_price_falls_back_to_stored_value(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create([
            'category_id' => $cat->id,
            'ticker' => 'XYZ',
            'quantity' => 2,
            'value' => 800,
            'date' => '2026-02-01',
        ]);
        // No AssetPrice row for XYZ.

        $result = app(ComputeValuesAsOf::class)->run('2026-02-15');

        $this->assertEqualsWithDelta(800.0, $result['byCategory'][$cat->id], 0.01);
    }

    public function test_excludes_categories_with_no_assets(): void
    {
        $withAsset = Category::factory()->create();
        $empty = Category::factory()->create();
        Asset::factory()->create(['category_id' => $withAsset->id, 'value' => 500, 'date' => '2026-02-01']);

        $result = app(ComputeValuesAsOf::class)->run('2026-02-15');

        $this->assertArrayHasKey($withAsset->id, $result['byCategory']);
        $this->assertArrayNotHasKey($empty->id, $result['byCategory']);
        $this->assertEqualsWithDelta(500.0, $result['total'], 0.01);
    }

    public function test_returns_zero_total_when_no_assets_exist(): void
    {
        Category::factory()->create();

        $result = app(ComputeValuesAsOf::class)->run('2026-02-15');

        $this->assertSame(0.0, $result['total']);
        $this->assertSame([], $result['byCategory']);
    }
}
