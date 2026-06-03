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

    private int $categoryId;

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

        $this->categoryId = Category::factory()->create()->id;
    }

    protected function tearDown(): void
    {
        Cache::forget('enable_banking.jwt');
        @unlink($this->keyPath);
        parent::tearDown();
    }

    /** A connected account linked to the logical asset "Conto" in the given category. */
    private function linkedAccount(string $uid, string $status = BankConnection::STATUS_ACTIVE, ?Carbon $validUntil = null): void
    {
        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut',
            'aspsp_country' => 'IT',
            'state' => 'state-'.$uid,
            'session_id' => 'sess-1',
            'status' => $status,
            'valid_until' => $validUntil ?? Carbon::now()->addDays(30),
        ]);
        $connection->accounts()->create([
            'uid' => $uid,
            'linked_name' => 'Conto',
            'linked_category_id' => $this->categoryId,
        ]);
    }

    private function fakeBalance(string $uid, string $amount): void
    {
        Http::fake([
            "api.enablebanking.com/accounts/{$uid}/balances" => Http::response([
                'balances' => [['balance_amount' => ['amount' => $amount, 'currency' => 'EUR']]],
            ]),
        ]);
    }

    public function test_updates_the_current_month_row_of_the_linked_asset(): void
    {
        $this->linkedAccount('acc-1');
        $asset = Asset::factory()->create([
            'category_id' => $this->categoryId, 'name' => 'Conto', 'value' => 100,
            'date' => now()->format('Y-m-01'),
        ]);
        $this->fakeBalance('acc-1', '1500.50');

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame(['Conto'], $result->updated);
        $asset->refresh();
        $this->assertEqualsWithDelta(1500.50, (float) $asset->value, 0.001);
        $this->assertNotNull($asset->bank_synced_at);
        $this->assertDatabaseHas('bank_accounts', ['uid' => 'acc-1', 'last_sync_status' => 'ok', 'last_sync_error' => null]);
    }

    public function test_creates_the_current_month_row_if_missing(): void
    {
        // No asset row for the current month yet — the sync should create it.
        $this->linkedAccount('acc-1');
        $this->fakeBalance('acc-1', '777.00');

        app(FetchBankBalances::class)->run();

        $this->assertDatabaseHas('assets', [
            'name' => 'Conto',
            'category_id' => $this->categoryId,
            'date' => now()->format('Y-m-01'),
            'value' => 777.0,
        ]);
    }

    public function test_input_payload_flags_a_linked_asset_as_bank_linked(): void
    {
        $this->linkedAccount('acc-1'); // links logical asset "Conto" in $categoryId
        $linked = Asset::factory()->create([
            'category_id' => $this->categoryId, 'name' => 'Conto', 'date' => now()->format('Y-m-01'),
        ]);
        $other = Asset::factory()->create([
            'category_id' => $this->categoryId, 'name' => 'Contanti', 'date' => now()->format('Y-m-01'),
        ]);

        $payload = app(FetchAssetsByMonth::class)->run(now()->format('Y-m-01'), AssetPrice::all()->keyBy('ticker'));

        $this->assertTrue($payload->firstWhere('id', $linked->id)['bank_linked']);
        $this->assertFalse($payload->firstWhere('id', $other->id)['bank_linked']);
    }

    public function test_follows_the_asset_into_a_new_month(): void
    {
        // Last month's row exists and was synced; the current month has none.
        $this->linkedAccount('acc-1');
        Asset::factory()->create([
            'category_id' => $this->categoryId, 'name' => 'Conto', 'value' => 500,
            'date' => now()->subMonthNoOverflow()->format('Y-m-01'), 'bank_synced_at' => now()->subMonth(),
        ]);
        $this->fakeBalance('acc-1', '650.00');

        app(FetchBankBalances::class)->run();

        // A fresh current-month row carries the new balance; last month is untouched.
        $this->assertDatabaseHas('assets', [
            'name' => 'Conto', 'date' => now()->format('Y-m-01'), 'value' => 650.0,
        ]);
        $this->assertDatabaseHas('assets', [
            'name' => 'Conto', 'date' => now()->subMonthNoOverflow()->format('Y-m-01'), 'value' => 500.0,
        ]);
    }

    public function test_failure_preserves_any_existing_value(): void
    {
        $this->linkedAccount('acc-1');
        $asset = Asset::factory()->create([
            'category_id' => $this->categoryId, 'name' => 'Conto', 'value' => 100,
            'date' => now()->format('Y-m-01'),
        ]);
        Http::fake(['api.enablebanking.com/accounts/acc-1/balances' => Http::response('', 500)]);

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame(['Conto'], $result->failed);
        $asset->refresh();
        $this->assertEqualsWithDelta(100.0, (float) $asset->value, 0.001);
        $this->assertNull($asset->bank_synced_at);
        // The failed attempt is recorded durably on the account.
        $this->assertDatabaseHas('bank_accounts', ['uid' => 'acc-1', 'last_sync_status' => 'failed']);
    }

    public function test_restores_a_trashed_row_instead_of_creating_a_duplicate(): void
    {
        // A bank-linked asset whose current-month row was deleted by the user.
        $this->linkedAccount('acc-1');
        $asset = Asset::factory()->create([
            'category_id' => $this->categoryId, 'name' => 'Conto', 'value' => 300,
            'date' => now()->format('Y-m-01'),
        ]);
        $asset->delete();
        $this->fakeBalance('acc-1', '450.00');

        app(FetchBankBalances::class)->run();

        // The same row is restored and updated — not duplicated.
        $this->assertNotSoftDeleted('assets', ['id' => $asset->id]);
        $this->assertSame(1, Asset::where('name', 'Conto')->where('category_id', $this->categoryId)->count());
        $asset->refresh();
        $this->assertEqualsWithDelta(450.0, (float) $asset->value, 0.001);
    }

    public function test_skips_expired_connections(): void
    {
        $this->linkedAccount('acc-1', BankConnection::STATUS_ACTIVE, Carbon::now()->subDay());
        $this->fakeBalance('acc-1', '999.00');

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame([], $result->updated);
        $this->assertDatabaseMissing('assets', ['name' => 'Conto', 'date' => now()->format('Y-m-01')]);
    }

    public function test_expires_the_connection_when_the_bank_rejects_the_session(): void
    {
        $this->linkedAccount('acc-1');
        Http::fake(['api.enablebanking.com/accounts/acc-1/balances' => Http::response('', 403)]);

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame(['Conto'], $result->failed);
        $this->assertDatabaseHas('bank_connections', [
            'state' => 'state-acc-1', 'status' => BankConnection::STATUS_EXPIRED,
        ]);
    }

    public function test_skips_unlinked_accounts(): void
    {
        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 'st', 'session_id' => 's',
            'status' => BankConnection::STATUS_ACTIVE, 'valid_until' => Carbon::now()->addDays(30),
        ]);
        $connection->accounts()->create(['uid' => 'acc-x']); // no linked_name/category

        $result = app(FetchBankBalances::class)->run();

        $this->assertSame([], $result->updated);
        $this->assertSame([], $result->failed);
    }
}
