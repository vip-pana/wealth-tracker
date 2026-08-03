<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Input\FetchInputData;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FetchInputDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_previous_values_map_the_prior_month_by_category_and_name(): void
    {
        $category = Category::factory()->create();
        Asset::factory()->create(['category_id' => $category->id, 'name' => 'ETF Mondo', 'value' => 1000, 'date' => '2026-05-01']);
        // Current month: the value being edited — must not leak into previousValues.
        Asset::factory()->create(['category_id' => $category->id, 'name' => 'ETF Mondo', 'value' => 1200, 'date' => '2026-06-01']);

        $data = app(FetchInputData::class)->run('2026-06-01');

        $this->assertSame(1000.0, $data['previousValues'][$category->id.'|ETF Mondo']);
    }

    public function test_previous_values_is_empty_for_the_earliest_month(): void
    {
        $category = Category::factory()->create();
        Asset::factory()->create(['category_id' => $category->id, 'name' => 'ETF Mondo', 'value' => 1000, 'date' => '2026-06-01']);

        $data = app(FetchInputData::class)->run('2026-06-01');

        $this->assertSame([], $data['previousValues']);
    }

    public function test_previous_value_of_a_quantity_held_asset_is_priced_not_read_from_the_column(): void
    {
        // A quantity-held asset keeps `value` at 0 and derives its figure from
        // quantity × price. Reading the raw column would report a previous value
        // of zero and make the asset look like a full gain every month.
        $category = Category::factory()->create();
        AssetPrice::create(['ticker' => 'VWCE', 'price' => 100.0, 'fetched_at' => now()]);

        foreach (['2026-05-01', '2026-06-01'] as $date) {
            Asset::factory()->create([
                'category_id' => $category->id,
                'name' => 'VWCE',
                'ticker' => 'VWCE',
                'quantity' => 10.0,
                'value' => 0,
                'date' => $date,
            ]);
        }

        $data = app(FetchInputData::class)->run('2026-06-01');

        $this->assertSame(1000.0, $data['previousValues'][$category->id.'|VWCE']);
    }

    public function test_previous_value_falls_back_to_the_column_without_a_price(): void
    {
        // No price row for the ticker: currentValue() falls back to the stored
        // value rather than producing zero.
        $category = Category::factory()->create();
        Asset::factory()->create([
            'category_id' => $category->id,
            'name' => 'Sconosciuto',
            'ticker' => 'NOPE',
            'quantity' => 5.0,
            'value' => 250,
            'date' => '2026-05-01',
        ]);
        Asset::factory()->create(['category_id' => $category->id, 'name' => 'Sconosciuto', 'value' => 300, 'date' => '2026-06-01']);

        $data = app(FetchInputData::class)->run('2026-06-01');

        $this->assertSame(250.0, $data['previousValues'][$category->id.'|Sconosciuto']);
    }

    public function test_position_returns_is_null_without_transaction_managed_assets(): void
    {
        Asset::factory()->create(['date' => '2026-06-01']);

        $data = app(FetchInputData::class)->run('2026-06-01');

        $this->assertArrayHasKey('positionReturns', $data);
        $this->assertNull($data['positionReturns']);
    }

    public function test_position_returns_spans_the_whole_history_not_the_requested_month(): void
    {
        // The position is built from transactions, which carry no month scoping:
        // asking for a month that holds no asset row still returns the position.
        $asset = Asset::factory()->create(['name' => 'ACWI', 'ticker' => 'ACWI', 'isin' => 'IE00B6R52259', 'date' => '2026-05-01']);
        Transaction::factory()->for($asset)->create([
            'type' => Transaction::TYPE_BUY, 'shares' => 10, 'price_per_share' => 100, 'date' => '2026-05-10',
        ]);
        AssetPrice::create(['ticker' => 'ACWI', 'price' => 120, 'currency' => 'EUR', 'fetched_at' => now()]);

        $data = app(FetchInputData::class)->run('2026-08-01');

        $this->assertNotNull($data['positionReturns']);
        $this->assertSame(1000.0, $data['positionReturns']['aggregate']['cost_basis']);
        $this->assertSame(1200.0, $data['positionReturns']['aggregate']['current_value']);
    }
}
