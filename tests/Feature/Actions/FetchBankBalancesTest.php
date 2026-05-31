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

    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);

        // The Enable Banking client signs a JWT with an RSA private key.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->keyPath = tempnam(sys_get_temp_dir(), 'eb_test_key');
        file_put_contents($this->keyPath, $pem);

        config([
            'services.enable_banking.application_id' => 'app-uuid',
            'services.enable_banking.private_key_path' => $this->keyPath,
        ]);
    }

    protected function tearDown(): void
    {
        Cache::forget('enable_banking.jwt');
        @unlink($this->keyPath);
        parent::tearDown();
    }

    private function linkedAsset(string $uid, float $value = 100): Asset
    {
        $cat = Category::factory()->create();

        return Asset::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Conto',
            'value' => $value,
            'bank_account_uid' => $uid,
            'date' => '2026-05-01',
        ]);
    }

    public function test_overwrites_value_with_the_live_balance(): void
    {
        $asset = $this->linkedAsset('acc-1', 100);

        Http::fake([
            'api.enablebanking.com/accounts/acc-1/balances' => Http::response([
                'balances' => [['balance_amount' => ['amount' => '1500.50', 'currency' => 'EUR']]],
            ]),
        ]);

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame(['Conto'], $result->updated);
        $asset->refresh();
        $this->assertEqualsWithDelta(1500.50, (float) $asset->value, 0.001);
        $this->assertNotNull($asset->bank_synced_at);
    }

    public function test_failure_preserves_the_existing_value(): void
    {
        $asset = $this->linkedAsset('acc-1', 100);

        Http::fake([
            'api.enablebanking.com/accounts/acc-1/balances' => Http::response('', 500),
        ]);

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame(['Conto'], $result->failed);
        $asset->refresh();
        $this->assertEqualsWithDelta(100.0, (float) $asset->value, 0.001);
        $this->assertNull($asset->bank_synced_at);
    }

    public function test_ignores_assets_without_a_linked_account(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create(['category_id' => $cat->id, 'bank_account_uid' => null, 'date' => '2026-05-01']);

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame([], $result->updated);
        $this->assertSame([], $result->failed);
    }
}
