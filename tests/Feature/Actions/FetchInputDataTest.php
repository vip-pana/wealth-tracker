<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Input\FetchInputData;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
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

    public function test_copyable_assets_lists_only_what_the_month_is_missing(): void
    {
        // The real case: last month held Bitcoin and a bank account, this month
        // only the bank account was carried over.
        $bank = Category::factory()->create(['name' => 'Liquidità']);
        $crypto = Category::factory()->create(['name' => 'Bitcoin']);
        Asset::factory()->create(['category_id' => $bank->id, 'name' => 'Conto', 'value' => 500, 'date' => '2026-07-01']);
        Asset::factory()->create(['category_id' => $crypto->id, 'name' => 'Wallet', 'value' => 900, 'date' => '2026-07-01']);
        Asset::factory()->create(['category_id' => $bank->id, 'name' => 'Conto', 'value' => 600, 'date' => '2026-08-01']);

        $data = app(FetchInputData::class)->run('2026-08-01');

        $this->assertSame('2026-07-01', $data['previousMonth']);
        $this->assertCount(1, $data['copyableAssets']);
        $this->assertSame('Wallet', $data['copyableAssets'][0]['name']);
        $this->assertSame($crypto->id, $data['copyableAssets'][0]['category_id']);
    }

    public function test_copyable_assets_is_empty_on_the_earliest_month(): void
    {
        $category = Category::factory()->create();
        Asset::factory()->create(['category_id' => $category->id, 'name' => 'Conto', 'date' => '2026-06-01']);

        $data = app(FetchInputData::class)->run('2026-06-01');

        $this->assertNull($data['previousMonth']);
        $this->assertSame([], $data['copyableAssets']);
    }

    public function test_copyable_asset_of_a_quantity_held_asset_carries_its_priced_value(): void
    {
        // Same trap as previousValues: the `value` column is 0 for these, so the
        // picker would offer "Bitcoin — 0 €" if it read the column.
        $category = Category::factory()->create();
        AssetPrice::create(['ticker' => 'BTC', 'price' => 50000.0, 'fetched_at' => now()]);
        Asset::factory()->create([
            'category_id' => $category->id,
            'name' => 'Bitcoin Wallet',
            'ticker' => 'BTC',
            'quantity' => 0.5,
            'value' => 0,
            'date' => '2026-07-01',
        ]);
        Asset::factory()->create(['category_id' => $category->id, 'name' => 'Altro', 'date' => '2026-08-01']);

        $data = app(FetchInputData::class)->run('2026-08-01');

        $this->assertSame(25000.0, $data['copyableAssets'][0]['value']);
    }

    public function test_copyable_assets_excludes_illiquid_categories(): void
    {
        $liquid = Category::factory()->create();
        $illiquid = Category::factory()->create(['macro_category' => 'Fondo Pensione']);
        Asset::factory()->create(['category_id' => $liquid->id, 'name' => 'Conto', 'date' => '2026-07-01']);
        Asset::factory()->create(['category_id' => $illiquid->id, 'name' => 'Pensione', 'date' => '2026-07-01']);
        Asset::factory()->create(['category_id' => $liquid->id, 'name' => 'Altro', 'date' => '2026-08-01']);

        $data = app(FetchInputData::class)->run('2026-08-01');

        $this->assertSame(['Conto'], array_column($data['copyableAssets'], 'name'));
    }

    public function test_does_not_carry_position_returns(): void
    {
        // The Bilancio is per-month asset bookkeeping and must not pay for a
        // whole-history computation. Per-position figures are reached from here
        // one asset at a time, through AssetTable's TransactionsDialog, which
        // already shows average cost, cost basis and P&L for that asset.
        Asset::factory()->create(['date' => '2026-06-01']);

        $data = app(FetchInputData::class)->run('2026-06-01');

        $this->assertArrayNotHasKey('positionReturns', $data);
    }
}
