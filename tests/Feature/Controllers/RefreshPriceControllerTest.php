<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshPriceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function etfAsset(string $ticker): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create([
            'category_id' => $cat->id,
            'ticker' => $ticker,
            'quantity' => 1,
            'date' => '2026-05-01',
        ]);
    }

    private function fakeYahoo(float $price): void
    {
        Http::fake([
            'query1.finance.yahoo.com/*' => Http::response([
                'chart' => ['result' => [['meta' => ['regularMarketPrice' => $price]]]],
            ]),
        ]);
    }

    public function test_flashes_success_when_all_prices_update(): void
    {
        $this->etfAsset('ISAC');
        $this->fakeYahoo(104.42);

        $this->post('/prices/refresh')
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        $this->assertDatabaseHas('asset_prices', ['ticker' => 'ISAC']);
    }

    public function test_flashes_generic_error_when_everything_fails(): void
    {
        // When nothing updates, the message stays generic rather than listing
        // every ticker (could be a long, ugly string). The per-ticker list is
        // reserved for partial failures (see the partial-success test below).
        $this->etfAsset('NOPE');
        Http::fake(['query1.finance.yahoo.com/*' => Http::response('', 500)]);

        $this->post('/prices/refresh')
            ->assertRedirect()
            ->assertSessionHas('error', 'Nessun valore aggiornato. Riprova più tardi.')
            ->assertSessionMissing('success');

        // A failed fetch now records the attempt (no price) rather than nothing.
        $this->assertDatabaseHas('asset_prices', ['ticker' => 'NOPE', 'last_status' => 'failed', 'price' => null]);
    }

    public function test_records_ok_status_on_success(): void
    {
        $this->etfAsset('ISAC');
        $this->fakeYahoo(104.42);

        $this->post('/prices/refresh')->assertRedirect();

        $this->assertDatabaseHas('asset_prices', [
            'ticker' => 'ISAC',
            'last_status' => 'ok',
        ]);
    }

    public function test_records_failed_status_and_keeps_no_price_on_failure(): void
    {
        $this->etfAsset('NOPE');
        Http::fake(['query1.finance.yahoo.com/*' => Http::response('', 500)]);

        $this->post('/prices/refresh')->assertRedirect();

        // A row is created to record the failed attempt, with no price.
        $this->assertDatabaseHas('asset_prices', [
            'ticker' => 'NOPE',
            'last_status' => 'failed',
            'price' => null,
        ]);
    }

    public function test_failure_does_not_wipe_a_previously_fetched_price(): void
    {
        // Seed a ticker that already has a good price from a prior fetch.
        AssetPrice::create([
            'ticker' => 'ISAC',
            'price' => 104.42,
            'currency' => 'EUR',
            'fetched_at' => now(),
            'last_status' => 'ok',
            'last_attempt_at' => now(),
        ]);
        $this->etfAsset('ISAC');

        // The next fetch fails for every candidate symbol.
        Http::fake(['query1.finance.yahoo.com/*' => Http::response('', 500)]);

        $this->post('/prices/refresh')->assertRedirect();

        // The old price survives; only the status flips to failed.
        $this->assertDatabaseHas('asset_prices', [
            'ticker' => 'ISAC',
            'last_status' => 'failed',
            'price' => 104.42,
        ]);
    }

    public function test_partial_success_reports_both_updated_and_failed(): void
    {
        $this->etfAsset('ISAC'); // will succeed
        $this->etfAsset('NOPE'); // will fail

        Http::fake([
            'query1.finance.yahoo.com/v8/finance/chart/ISAC*' => Http::response([
                'chart' => ['result' => [['meta' => ['regularMarketPrice' => 104.42]]]],
            ]),
            'query1.finance.yahoo.com/*' => Http::response('', 500),
        ]);

        $this->post('/prices/refresh')->assertRedirect();

        $error = session('error');
        $this->assertIsString($error);
        $this->assertStringContainsString('NOPE', $error);
        $this->assertDatabaseHas('asset_prices', ['ticker' => 'ISAC', 'last_status' => 'ok']);
        $this->assertDatabaseHas('asset_prices', ['ticker' => 'NOPE', 'last_status' => 'failed', 'price' => null]);
    }
}
