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

    public function test_authorizes_a_session_and_returns_accounts_with_real_validity(): void
    {
        Http::fake([
            'api.enablebanking.com/sessions' => Http::response([
                'session_id' => 'sess-1',
                'accounts' => [
                    ['uid' => 'acc-uid-1', 'name' => 'Main', 'currency' => 'EUR'],
                ],
                'access' => ['valid_until' => '2026-08-01T10:00:00Z'],
            ]),
        ]);

        $result = $this->client()->authorizeSession('code-abc');

        $this->assertSame('sess-1', $result['session_id']);
        $this->assertSame('acc-uid-1', $result['accounts'][0]['uid']);
        $this->assertNotNull($result['valid_until']);
        $this->assertSame('2026-08-01', $result['valid_until']->format('Y-m-d'));
    }

    public function test_session_validity_is_null_when_the_bank_omits_it(): void
    {
        Http::fake([
            'api.enablebanking.com/sessions' => Http::response([
                'session_id' => 'sess-1',
                'accounts' => [['uid' => 'acc-uid-1']],
            ]),
        ]);

        $result = $this->client()->authorizeSession('code-abc');

        $this->assertNull($result['valid_until']);
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

    public function test_balance_reports_unauthorized_on_a_rejected_session(): void
    {
        Http::fake(['api.enablebanking.com/accounts/*/balances' => Http::response('', 403)]);

        $this->assertSame('unauthorized', $this->client()->accountBalance('acc-uid-1'));
    }

    public function test_returns_empty_when_the_private_key_is_missing(): void
    {
        $client = new EnableBankingClient('app-uuid', '/nonexistent/key.pem');

        $this->assertSame([], $client->aspsps('IT'));
        $this->assertNull($client->accountBalance('acc-uid-1'));
    }

    public function test_normalises_transactions_with_a_signed_amount(): void
    {
        Http::fake([
            'api.enablebanking.com/accounts/acc-1/transactions*' => Http::response([
                'transactions' => [
                    [
                        'entry_reference' => 'tx-1',
                        'transaction_amount' => ['amount' => '12.50', 'currency' => 'EUR'],
                        'credit_debit_indicator' => 'DBIT',
                        'booking_date' => '2026-07-01',
                        'value_date' => '2026-07-02',
                        'remittance_information' => 'Coffee',
                        'creditor' => 'Bar Rossi',
                        'merchant_category_code' => '5812',
                    ],
                    [
                        'entry_reference' => 'tx-2',
                        'transaction_amount' => ['amount' => '1800.00', 'currency' => 'EUR'],
                        'credit_debit_indicator' => 'CRDT',
                        'booking_date' => '2026-07-01',
                        'debtor' => 'ACME SpA',
                    ],
                ],
                'continuation_key' => null,
            ]),
        ]);

        $page = $this->client()->transactions('acc-1');

        $this->assertNull($page['next_key']);
        $this->assertCount(2, $page['items']);

        $debit = $page['items'][0];
        $this->assertSame('tx-1', $debit['external_id']);
        $this->assertEqualsWithDelta(-12.50, $debit['amount'], 0.001);
        $this->assertSame('Coffee', $debit['description']);
        $this->assertSame('Bar Rossi', $debit['counterparty']);
        $this->assertSame('5812', $debit['merchant_category_code']);

        $credit = $page['items'][1];
        $this->assertEqualsWithDelta(1800.00, $credit['amount'], 0.001);
        $this->assertSame('ACME SpA', $credit['counterparty']);
    }

    public function test_transactions_passes_the_continuation_key_through(): void
    {
        Http::fake([
            'api.enablebanking.com/accounts/acc-1/transactions*' => Http::response([
                'transactions' => [], 'continuation_key' => 'next-page',
            ]),
        ]);

        $page = $this->client()->transactions('acc-1', 'cursor-abc');

        $this->assertSame('next-page', $page['next_key']);
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'continuation_key=cursor-abc'));
    }

    public function test_transactions_skips_items_without_a_stable_id(): void
    {
        Http::fake([
            'api.enablebanking.com/accounts/acc-1/transactions*' => Http::response([
                'transactions' => [
                    ['transaction_amount' => ['amount' => '1.00', 'currency' => 'EUR'], 'credit_debit_indicator' => 'DBIT', 'booking_date' => '2026-07-01'],
                ],
                'continuation_key' => null,
            ]),
        ]);

        $page = $this->client()->transactions('acc-1');

        $this->assertSame([], $page['items']);
    }

    public function test_transactions_reports_unauthorized_on_a_rejected_session(): void
    {
        Http::fake(['api.enablebanking.com/accounts/*/transactions*' => Http::response('', 403)]);

        $this->assertSame('unauthorized', $this->client()->transactions('acc-1'));
    }

    public function test_transactions_reports_rate_limited_on_429(): void
    {
        Http::fake(['api.enablebanking.com/accounts/*/transactions*' => Http::response(['error' => 'ASPSP_RATE_LIMIT_EXCEEDED'], 429)]);

        $this->assertSame('rate_limited', $this->client()->transactions('acc-1'));
    }

    public function test_transactions_returns_null_on_failure(): void
    {
        Http::fake(['api.enablebanking.com/accounts/*/transactions*' => Http::response('', 500)]);

        $this->assertNull($this->client()->transactions('acc-1'));
    }
}
