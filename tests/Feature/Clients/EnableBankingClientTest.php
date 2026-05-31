<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Http\Clients\EnableBankingClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnableBankingClientTest extends TestCase
{
    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->keyPath = tempnam(sys_get_temp_dir(), 'eb_test_key');
        file_put_contents($this->keyPath, $pem);
    }

    protected function tearDown(): void
    {
        Cache::forget('enable_banking.jwt');
        @unlink($this->keyPath);
        parent::tearDown();
    }

    private function client(): EnableBankingClient
    {
        return new EnableBankingClient('app-uuid', $this->keyPath);
    }

    public function test_sends_a_signed_bearer_jwt(): void
    {
        Http::fake(['api.enablebanking.com/aspsps*' => Http::response(['aspsps' => []])]);

        $this->client()->aspsps('IT');

        Http::assertSent(function ($request): bool {
            $auth = $request->header('Authorization')[0] ?? '';

            // A three-part JWT (header.payload.signature) as a Bearer token.
            return str_starts_with($auth, 'Bearer ')
                && substr_count(substr($auth, 7), '.') === 2;
        });
    }

    public function test_lists_aspsps_for_a_country(): void
    {
        Http::fake([
            'api.enablebanking.com/aspsps*' => Http::response([
                'aspsps' => [
                    ['name' => 'Intesa Sanpaolo', 'country' => 'IT'],
                    ['name' => 'N26', 'country' => 'IT'],
                ],
            ]),
        ]);

        $aspsps = $this->client()->aspsps('IT');

        $this->assertCount(2, $aspsps);
        $this->assertSame('Intesa Sanpaolo', $aspsps[0]['name']);
    }

    public function test_starts_an_authorization_and_returns_the_consent_url(): void
    {
        Http::fake([
            'api.enablebanking.com/auth' => Http::response([
                'url' => 'https://tilisy.enablebanking.com/welcome?sessionid=x',
                'authorization_id' => 'auth-123',
            ]),
        ]);

        $result = $this->client()->startAuthorization('N26', 'IT', 'https://app/callback', 'state-1');

        $this->assertSame('https://tilisy.enablebanking.com/welcome?sessionid=x', $result['url']);
        $this->assertSame('auth-123', $result['authorization_id']);
    }

    public function test_authorizes_a_session_and_returns_accounts(): void
    {
        Http::fake([
            'api.enablebanking.com/sessions' => Http::response([
                'session_id' => 'sess-1',
                'accounts' => [
                    ['uid' => 'acc-uid-1', 'name' => 'Main', 'currency' => 'EUR'],
                ],
            ]),
        ]);

        $result = $this->client()->authorizeSession('code-abc');

        $this->assertSame('sess-1', $result['session_id']);
        $this->assertSame('acc-uid-1', $result['accounts'][0]['uid']);
    }

    public function test_reads_an_account_balance(): void
    {
        Http::fake([
            'api.enablebanking.com/accounts/acc-uid-1/balances' => Http::response([
                'balances' => [['balance_amount' => ['amount' => '2500.00', 'currency' => 'EUR']]],
            ]),
        ]);

        $balance = $this->client()->accountBalance('acc-uid-1');

        $this->assertEqualsWithDelta(2500.0, $balance['amount'], 0.001);
        $this->assertSame('EUR', $balance['currency']);
    }

    public function test_balance_returns_null_on_unexpected_shape(): void
    {
        Http::fake(['api.enablebanking.com/accounts/*/balances' => Http::response(['balances' => [['wrong' => true]]])]);

        $this->assertNull($this->client()->accountBalance('acc-uid-1'));
    }

    public function test_returns_empty_when_the_private_key_is_missing(): void
    {
        $client = new EnableBankingClient('app-uuid', '/nonexistent/key.pem');

        $this->assertSame([], $client->aspsps('IT'));
        $this->assertNull($client->accountBalance('acc-uid-1'));
    }
}
