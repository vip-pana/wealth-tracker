<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Input\FetchAssetsByMonth;
use App\Actions\Prices\FetchBankBalances;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\BankConnection;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    /**
     * An asset linked, through an active connection, to a bank account uid.
     */
    private function linkedAsset(string $uid, float $value, string $status = BankConnection::STATUS_ACTIVE, ?Carbon $validUntil = null): Asset
    {
        $cat = Category::factory()->create();
        $asset = Asset::factory()->create(['category_id' => $cat->id, 'name' => 'Conto', 'value' => $value, 'date' => '2026-05-01']);

        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut',
            'aspsp_country' => 'IT',
            'state' => 'state-'.$uid,
            'session_id' => 'sess-1',
            'status' => $status,
            'valid_until' => $validUntil ?? Carbon::now()->addDays(30),
        ]);
        $connection->accounts()->create(['uid' => $uid, 'asset_id' => $asset->id]);

        return $asset;
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

    public function test_input_payload_exposes_bank_synced_at(): void
    {
        $asset = $this->linkedAsset('acc-1', 100);
        Http::fake([
            'api.enablebanking.com/accounts/acc-1/balances' => Http::response([
                'balances' => [['balance_amount' => ['amount' => '1500.50', 'currency' => 'EUR']]],
            ]),
        ]);
        app(FetchBankBalances::class)->run();

        $payload = app(FetchAssetsByMonth::class)->run($asset->date->format('Y-m-01'), AssetPrice::all()->keyBy('ticker'));
        $row = $payload->firstWhere('id', $asset->id);

        $this->assertNotNull($row['bank_synced_at']);
    }

    public function test_failure_preserves_the_existing_value(): void
    {
        $asset = $this->linkedAsset('acc-1', 100);

        Http::fake(['api.enablebanking.com/accounts/acc-1/balances' => Http::response('', 500)]);

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame(['Conto'], $result->failed);
        $asset->refresh();
        $this->assertEqualsWithDelta(100.0, (float) $asset->value, 0.001);
        $this->assertNull($asset->bank_synced_at);
    }

    public function test_skips_expired_connections(): void
    {
        $asset = $this->linkedAsset('acc-1', 100, BankConnection::STATUS_ACTIVE, Carbon::now()->subDay());

        Http::fake(['api.enablebanking.com/*' => Http::response(['balances' => [['balance_amount' => ['amount' => '999', 'currency' => 'EUR']]]])]);

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame([], $result->updated);
        $this->assertSame([], $result->failed);
        $asset->refresh();
        $this->assertEqualsWithDelta(100.0, (float) $asset->value, 0.001);
    }

    public function test_skips_accounts_not_linked_to_an_asset(): void
    {
        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 'st', 'session_id' => 's', 'status' => BankConnection::STATUS_ACTIVE, 'valid_until' => Carbon::now()->addDays(30),
        ]);
        $connection->accounts()->create(['uid' => 'acc-x', 'asset_id' => null]);

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame([], $result->updated);
        $this->assertSame([], $result->failed);
    }
}
