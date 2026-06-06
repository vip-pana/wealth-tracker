<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScalableRefreshControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function configure(): void
    {
        config([
            'services.scalable.balance_url' => 'http://scalable.test',
            'services.scalable.token' => 'tok',
            'services.scalable.cash_category_id' => 0,
        ]);
    }

    public function test_flashes_success_when_a_holding_syncs(): void
    {
        $this->configure();
        $cat = Category::factory()->create();
        Asset::factory()->create(['category_id' => $cat->id, 'name' => 'ACWI', 'isin' => 'IE00B6R52259', 'date' => now()->format('Y-m-01')]);
        Http::fake([
            'scalable.test/portfolio/inventory' => Http::response([
                'account' => ['brokerPortfolio' => ['inventory' => ['ungroupedInventoryItems' => ['items' => [
                    ['isin' => 'IE00B6R52259', 'name' => 'ACWI', 'inventory' => ['position' => ['filled' => 10.0]], 'quoteTick' => ['midPrice' => 50.0]],
                ]]]]],
            ]),
        ]);

        $this->post('/scalable/refresh')
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('error');
    }

    public function test_flashes_error_when_the_proxy_fails(): void
    {
        $this->configure();
        Http::fake(['scalable.test/*' => Http::response('', 500)]);

        $this->post('/scalable/refresh')
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_flashes_error_when_unconfigured(): void
    {
        config(['services.scalable.balance_url' => '']);

        $this->post('/scalable/refresh')
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
