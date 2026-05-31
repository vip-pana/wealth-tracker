<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

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
