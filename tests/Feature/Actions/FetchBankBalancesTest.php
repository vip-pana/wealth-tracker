<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Prices\FetchBankBalances;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchBankBalancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        config(['services.gocardless.secret_id' => 'id', 'services.gocardless.secret_key' => 'key']);
    }

    protected function tearDown(): void
    {
        Cache::forget('gocardless.access_token');
        parent::tearDown();
    }

    private function fakeToken(): array
    {
        return [
            'bankaccountdata.gocardless.com/api/v2/token/new/' => Http::response([
                'access' => 'TOKEN',
                'access_expires' => 86400,
            ]),
        ];
    }

    private function linkedAsset(string $accountId, float $value = 100): Asset
    {
        $cat = Category::factory()->create();

        return Asset::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Conto',
            'value' => $value,
            'gocardless_account_id' => $accountId,
            'date' => '2026-05-01',
        ]);
    }

    public function test_overwrites_value_with_the_live_balance(): void
    {
        $asset = $this->linkedAsset('acc-1', 100);

        Http::fake(array_merge($this->fakeToken(), [
            'bankaccountdata.gocardless.com/api/v2/accounts/acc-1/balances/' => Http::response([
                'balances' => [['balanceAmount' => ['amount' => '1500.50', 'currency' => 'EUR']]],
            ]),
        ]));

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame(['Conto'], $result->updated);
        $asset->refresh();
        $this->assertEqualsWithDelta(1500.50, (float) $asset->value, 0.001);
        $this->assertNotNull($asset->gocardless_synced_at);
    }

    public function test_failure_preserves_the_existing_value(): void
    {
        $asset = $this->linkedAsset('acc-1', 100);

        Http::fake(array_merge($this->fakeToken(), [
            'bankaccountdata.gocardless.com/api/v2/accounts/acc-1/balances/' => Http::response('', 500),
        ]));

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame(['Conto'], $result->failed);
        $asset->refresh();
        $this->assertEqualsWithDelta(100.0, (float) $asset->value, 0.001);
        $this->assertNull($asset->gocardless_synced_at);
    }

    public function test_ignores_assets_without_a_linked_account(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create(['category_id' => $cat->id, 'gocardless_account_id' => null, 'date' => '2026-05-01']);

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame([], $result->updated);
        $this->assertSame([], $result->failed);
    }
}
