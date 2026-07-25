<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use App\Models\Goal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Enable Banking is unconfigured in these tests; the bank list must
        // resolve to empty without any outbound HTTP call.
        config(['cache.default' => 'array']);
        config(['services.enable_banking.application_id' => '', 'services.enable_banking.private_key_path' => '']);
        Http::preventStrayRequests();
    }

    public function test_lists_trashed_assets_categories_and_goals(): void
    {
        $cat = Category::factory()->create(['name' => 'Liquidità']);
        $asset = Asset::factory()->create(['category_id' => $cat->id, 'name' => 'Conto']);
        $goal = Goal::create(['name' => 'FIRE', 'target_value' => 100000]);

        $asset->delete();
        $goal->delete();
        $deletableCat = Category::factory()->create(['name' => 'Vecchia']);
        $deletableCat->delete();

        $this->get('/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings')
                ->has('trashed', 3)
            );
    }

    public function test_trashed_is_empty_when_nothing_deleted(): void
    {
        Category::factory()->create();

        $this->get('/settings')
            ->assertInertia(fn ($page) => $page
                ->component('Settings')
                ->has('trashed', 0)
            );
    }

    public function test_linkable_assets_are_only_manual_liquidity_of_the_current_month(): void
    {
        $liquidity = Category::factory()->create(['macro_category' => MacroCategory::Liquidita->value]);
        $crypto = Category::factory()->create(['macro_category' => MacroCategory::Cripto->value]);

        // Eligible: manual liquidity asset this month.
        Asset::factory()->create(['category_id' => $liquidity->id, 'name' => 'Conto', 'ticker' => null, 'date' => now()->format('Y-m-01')]);
        // Excluded: manual but in a crypto category (not a bank account).
        Asset::factory()->create(['category_id' => $crypto->id, 'name' => 'Bitcoin a mano', 'ticker' => null, 'date' => now()->format('Y-m-01')]);
        // Excluded: liquidity but priced via a ticker.
        Asset::factory()->create(['category_id' => $liquidity->id, 'name' => 'Ticker', 'ticker' => 'XEON.MI', 'date' => now()->format('Y-m-01')]);
        // Excluded: liquidity but last month.
        Asset::factory()->create(['category_id' => $liquidity->id, 'name' => 'Vecchio', 'ticker' => null, 'date' => now()->subMonthNoOverflow()->format('Y-m-01')]);

        $this->get('/settings')
            ->assertInertia(fn ($page) => $page
                ->component('Settings')
                ->has('linkableAssets', 1)
                ->where('linkableAssets.0.name', 'Conto')
            );
    }

    public function test_prices_expose_fetch_status(): void
    {
        AssetPrice::create([
            'ticker' => 'BTC',
            'price' => 60000,
            'currency' => 'EUR',
            'fetched_at' => now(),
            'last_status' => 'ok',
            'last_attempt_at' => now(),
        ]);
        AssetPrice::create([
            'ticker' => 'NOPE',
            'currency' => 'EUR',
            'last_status' => 'failed',
            'last_attempt_at' => now(),
            'last_error' => 'Prezzo non disponibile.',
        ]);

        $this->get('/settings')
            ->assertInertia(fn ($page) => $page
                ->component('Settings')
                ->has('prices', 2)
                // ordered by ticker: BTC, NOPE
                ->where('prices.0.ticker', 'BTC')
                ->where('prices.0.last_status', 'ok')
                ->where('prices.1.ticker', 'NOPE')
                ->where('prices.1.last_status', 'failed')
                ->where('prices.1.price', null)
            );
    }
}
