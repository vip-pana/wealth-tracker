<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Prices\FetchAllPrices;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchAllPricesCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_prices_for_tickers_no_active_asset_uses_are_purged(): void
    {
        // No outbound calls should reach a real API during the run.
        Http::fake(['*' => Http::response('', 503)]);

        $cat = Category::factory()->create();
        // An active asset still using ISAC; its price must survive.
        Asset::factory()->create([
            'category_id' => $cat->id, 'name' => 'ACWI', 'ticker' => 'ISAC',
            'quantity' => 1, 'value' => 100, 'date' => Carbon::today()->format('Y-m-01'),
        ]);

        AssetPrice::recordSuccess('ISAC', 105.0, 'EUR');
        // Orphan: a deleted holding's leftover price, no active asset uses it.
        AssetPrice::recordSuccess('EUNH.DE', 110.0, 'EUR');

        app(FetchAllPrices::class)->run();

        $this->assertDatabaseHas('asset_prices', ['ticker' => 'ISAC']);
        $this->assertDatabaseMissing('asset_prices', ['ticker' => 'EUNH.DE']);
    }

    public function test_all_orphan_prices_are_purged_when_no_asset_has_a_ticker(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        AssetPrice::recordSuccess('ISAC', 105.0, 'EUR');
        AssetPrice::recordSuccess('SGLD', 360.0, 'EUR');

        app(FetchAllPrices::class)->run();

        $this->assertSame(0, AssetPrice::count());
    }
}
