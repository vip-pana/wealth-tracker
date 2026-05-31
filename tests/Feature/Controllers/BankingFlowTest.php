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

    public function test_reconnect_prunes_the_old_expired_connection_for_the_same_bank(): void
    {
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

        $this->assertDatabaseMissing('bank_connections', ['state' => 'old']);
        $this->assertSame(1, BankConnection::where('aspsp_name', 'Revolut')->count());
        $this->assertDatabaseHas('bank_connections', ['aspsp_name' => 'Revolut', 'status' => 'pending']);
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

    public function test_callback_rejects_an_unknown_state(): void
    {
        $this->get('/banking/callback?code=x&state=nonexistent')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_links_an_account_to_an_asset(): void
    {
        $cat = Category::factory()->create();
        $asset = Asset::factory()->create(['category_id' => $cat->id, 'date' => '2026-05-01']);
        $connection = BankConnection::create(['aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 's', 'status' => 'active']);
        $account = $connection->accounts()->create(['uid' => 'acc-1']);

        $this->post("/banking/accounts/{$account->id}/link", ['asset_id' => $asset->id])
            ->assertRedirect();

        $this->assertDatabaseHas('bank_accounts', ['id' => $account->id, 'asset_id' => $asset->id]);
    }

    public function test_disconnect_removes_the_connection_and_its_accounts(): void
    {
        $connection = BankConnection::create(['aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 's', 'status' => 'active']);
        $account = $connection->accounts()->create(['uid' => 'acc-1']);

        $this->delete("/banking/connections/{$connection->id}")->assertRedirect();

        $this->assertDatabaseMissing('bank_connections', ['id' => $connection->id]);
        $this->assertDatabaseMissing('bank_accounts', ['id' => $account->id]);
    }
}
