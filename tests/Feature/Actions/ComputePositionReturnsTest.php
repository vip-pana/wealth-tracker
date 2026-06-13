<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Advisor\ComputePositionReturns;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComputePositionReturnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_no_asset_has_transactions(): void
    {
        Asset::factory()->create();

        $this->assertNull(app(ComputePositionReturns::class)->run());
    }

    public function test_computes_per_position_and_aggregate_returns(): void
    {
        $acwi = Asset::factory()->create(['name' => 'ACWI', 'ticker' => 'ACWI', 'isin' => 'IE00B6R52259']);
        // 20 shares at an average cost of 100 (cost basis 2000).
        Transaction::factory()->for($acwi)->create(['type' => Transaction::TYPE_BUY, 'shares' => 10, 'price_per_share' => 90, 'date' => '2025-01-01']);
        Transaction::factory()->for($acwi)->create(['type' => Transaction::TYPE_BUY, 'shares' => 10, 'price_per_share' => 110, 'date' => '2025-02-01']);
        AssetPrice::create(['ticker' => 'ACWI', 'price' => 120, 'currency' => 'EUR', 'fetched_at' => now()]);

        $result = app(ComputePositionReturns::class)->run();

        $this->assertNotNull($result);
        $this->assertCount(1, $result['positions']);

        $pos = $result['positions'][0];
        $this->assertSame('ACWI', $pos['name']);
        $this->assertSame(2000.0, $pos['cost_basis']);
        $this->assertSame(2400.0, $pos['current_value']);       // 20 * 120
        $this->assertSame(400.0, $pos['unrealised_pnl']);       // 2400 - 2000
        $this->assertSame(20.0, $pos['unrealised_pnl_pct']);    // 400 / 2000

        $agg = $result['aggregate'];
        $this->assertSame(2000.0, $agg['cost_basis']);
        $this->assertSame(2400.0, $agg['current_value']);
        $this->assertSame(400.0, $agg['unrealised_pnl']);
        $this->assertSame(20.0, $agg['unrealised_pnl_pct']);
    }

    public function test_aggregates_realised_pnl_across_positions(): void
    {
        $a = Asset::factory()->create(['ticker' => 'AAA', 'isin' => 'IE0000000001']);
        Transaction::factory()->for($a)->create(['type' => Transaction::TYPE_BUY, 'shares' => 10, 'price_per_share' => 100, 'date' => '2025-01-01']);
        Transaction::factory()->for($a)->sell()->create(['shares' => 5, 'price_per_share' => 150, 'date' => '2025-02-01']); // +250 realised
        AssetPrice::create(['ticker' => 'AAA', 'price' => 100, 'currency' => 'EUR', 'fetched_at' => now()]);

        $b = Asset::factory()->create(['ticker' => 'BBB', 'isin' => 'IE0000000002']);
        Transaction::factory()->for($b)->create(['type' => Transaction::TYPE_BUY, 'shares' => 10, 'price_per_share' => 50, 'date' => '2025-01-01']);
        Transaction::factory()->for($b)->sell()->create(['shares' => 10, 'price_per_share' => 40, 'date' => '2025-02-01']); // -100 realised
        AssetPrice::create(['ticker' => 'BBB', 'price' => 40, 'currency' => 'EUR', 'fetched_at' => now()]);

        $result = app(ComputePositionReturns::class)->run();

        $this->assertNotNull($result);
        $this->assertCount(2, $result['positions']);
        $this->assertSame(150.0, $result['aggregate']['realised_pnl']); // 250 - 100
    }

    public function test_unpriced_position_still_contributes_cost_but_not_value(): void
    {
        $a = Asset::factory()->create(['ticker' => 'NOPRICE', 'isin' => 'IE0000000003']);
        Transaction::factory()->for($a)->create(['type' => Transaction::TYPE_BUY, 'shares' => 10, 'price_per_share' => 100, 'date' => '2025-01-01']);
        // No AssetPrice row → can't value it.

        $result = app(ComputePositionReturns::class)->run();

        $this->assertNotNull($result);
        $this->assertNull($result['positions'][0]['current_value']);
        $this->assertSame(1000.0, $result['aggregate']['cost_basis']);
        $this->assertSame(0.0, $result['aggregate']['current_value']);
    }
}
