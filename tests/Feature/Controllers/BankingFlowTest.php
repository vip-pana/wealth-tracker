<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Asset;
use App\Models\BankConnection;
use App\Models\Category;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BankingFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['cache.default' => 'array']);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->keyPath = tempnam(sys_get_temp_dir(), 'eb_test_key');
        file_put_contents($this->keyPath, $pem);

        config([
            'services.enable_banking.application_id' => 'app-uuid',
            'services.enable_banking.private_key_path' => $this->keyPath,
            'services.enable_banking.redirect_url' => 'https://example.test/banking/callback',
        ]);
    }

    protected function tearDown(): void
    {
        Cache::forget('enable_banking.jwt');
        @unlink($this->keyPath);
        parent::tearDown();
    }

    public function test_connect_creates_a_pending_connection_and_redirects_to_consent(): void
    {
        Http::fake([
            'api.enablebanking.com/auth' => Http::response([
                'url' => 'https://tilisy.enablebanking.com/ais/start?sessionid=x',
                'authorization_id' => 'auth-1',
            ]),
        ]);

        $this->post('/banking/connect', ['aspsp_name' => 'Revolut', 'aspsp_country' => 'IT'])
            ->assertRedirect('https://tilisy.enablebanking.com/ais/start?sessionid=x');

        $this->assertDatabaseHas('bank_connections', ['aspsp_name' => 'Revolut', 'status' => 'pending']);
    }

    public function test_connect_keeps_the_old_expired_connection_until_callback(): void
    {
        // Expired connections are kept through /connect so the callback can
        // inherit their links; only stale pending attempts are pruned now.
        BankConnection::create([
            'aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 'old', 'status' => 'expired',
        ]);

        Http::fake([
            'api.enablebanking.com/auth' => Http::response([
                'url' => 'https://tilisy.enablebanking.com/ais/start?sessionid=y',
                'authorization_id' => 'auth-2',
            ]),
        ]);

        $this->post('/banking/connect', ['aspsp_name' => 'Revolut', 'aspsp_country' => 'IT'])->assertRedirect();

        $this->assertDatabaseHas('bank_connections', ['state' => 'old', 'status' => 'expired']);
        $this->assertDatabaseHas('bank_connections', ['aspsp_name' => 'Revolut', 'status' => 'pending']);
    }

    public function test_reconnect_inherits_the_asset_link_by_iban_and_prunes_the_old_connection(): void
    {
        // An old expired connection whose account was linked to an asset.
        $old = BankConnection::create([
            'aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 'old', 'status' => 'expired',
        ]);
        $cat = Category::factory()->create();
        $old->accounts()->create([
            'uid' => 'old-uid',
            'iban' => 'IT00REVOLUT0001',
            'linked_name' => 'Conto Revolut',
            'linked_category_id' => $cat->id,
        ]);

        // A fresh pending connection (as if /connect just ran) about to be confirmed.
        BankConnection::create([
            'aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 'fresh', 'status' => 'pending',
        ]);

        // The bank returns the same IBAN but a NEW uid.
        Http::fake([
            'api.enablebanking.com/sessions' => Http::response([
                'session_id' => 'sess-new',
                'accounts' => [
                    ['uid' => 'new-uid', 'account_id' => ['iban' => 'IT00REVOLUT0001'], 'name' => 'Main', 'currency' => 'EUR'],
                ],
            ]),
        ]);

        $this->get('/banking/callback?code=c&state=fresh')->assertRedirect();

        // The new account inherited the link; the old connection is gone.
        $this->assertDatabaseHas('bank_accounts', [
            'uid' => 'new-uid', 'linked_name' => 'Conto Revolut', 'linked_category_id' => $cat->id,
        ]);
        $this->assertDatabaseMissing('bank_connections', ['state' => 'old']);
        $this->assertSame(1, BankConnection::where('aspsp_name', 'Revolut')->count());
    }

    public function test_callback_exchanges_code_and_persists_session_and_accounts(): void
    {
        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 'the-state', 'status' => 'pending',
        ]);

        Http::fake([
            'api.enablebanking.com/sessions' => Http::response([
                'session_id' => 'sess-99',
                'accounts' => [
                    ['uid' => 'acc-uid-1', 'account_id' => ['iban' => 'IT60X0542811101000000123456'], 'name' => 'Main', 'currency' => 'EUR'],
                ],
            ]),
        ]);

        $this->get('/banking/callback?code=the-code&state=the-state')
            ->assertRedirect(route('settings.index', absolute: false));

        $connection->refresh();
        $this->assertSame('active', $connection->status);
        $this->assertSame('sess-99', $connection->session_id);
        $this->assertNotNull($connection->valid_until);
        $this->assertDatabaseHas('bank_accounts', ['uid' => 'acc-uid-1', 'iban' => 'IT60X0542811101000000123456']);
    }

    public function test_callback_honours_the_banks_real_session_validity(): void
    {
        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 'st-validity', 'status' => 'pending',
        ]);

        Http::fake([
            'api.enablebanking.com/sessions' => Http::response([
                'session_id' => 'sess-v',
                'accounts' => [['uid' => 'acc-v']],
                // Bank caps the window well below the 90 days we requested.
                'access' => ['valid_until' => now()->addDays(7)->toIso8601ZuluString()],
            ]),
        ]);

        $this->get('/banking/callback?code=c&state=st-validity')->assertRedirect();

        $connection->refresh();
        $this->assertNotNull($connection->valid_until);
        $this->assertSame(now()->addDays(7)->format('Y-m-d'), $connection->valid_until->format('Y-m-d'));
    }

    public function test_callback_rejects_an_unknown_state(): void
    {
        $this->get('/banking/callback?code=x&state=nonexistent')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_links_an_account_to_an_assets_logical_identity(): void
    {
        $cat = Category::factory()->create();
        $asset = Asset::factory()->create(['category_id' => $cat->id, 'name' => 'Conto', 'date' => '2026-05-01']);
        $connection = BankConnection::create(['aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 's', 'status' => 'active']);
        $account = $connection->accounts()->create(['uid' => 'acc-1']);

        $this->post("/banking/accounts/{$account->id}/link", ['asset_id' => $asset->id])
            ->assertRedirect();

        // Stored by name + category (logical), not by the row's id.
        $this->assertDatabaseHas('bank_accounts', [
            'id' => $account->id, 'linked_name' => 'Conto', 'linked_category_id' => $cat->id,
        ]);
    }

    public function test_disconnect_removes_the_connection_and_its_accounts(): void
    {
        $connection = BankConnection::create(['aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 's', 'status' => 'active']);
        $account = $connection->accounts()->create(['uid' => 'acc-1']);

        $this->delete("/banking/connections/{$connection->id}")->assertRedirect();

        $this->assertDatabaseMissing('bank_connections', ['id' => $connection->id]);
        $this->assertDatabaseMissing('bank_accounts', ['id' => $account->id]);
    }

    public function test_disconnect_clears_bank_synced_at_on_the_linked_assets(): void
    {
        $category = Category::factory()->create();
        $connection = BankConnection::create(['aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 's', 'status' => 'active']);
        $connection->accounts()->create(['uid' => 'acc-1', 'linked_name' => 'Conto', 'linked_category_id' => $category->id]);
        $asset = Asset::factory()->create([
            'name' => 'Conto', 'category_id' => $category->id, 'bank_synced_at' => now(),
        ]);

        $this->delete("/banking/connections/{$connection->id}")->assertRedirect();

        $asset->refresh();
        $this->assertNull($asset->bank_synced_at);
    }
}
