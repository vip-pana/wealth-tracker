<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Http\Clients\GoCardlessClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoCardlessClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The token is cached; use the array store so tests don't need the DB cache table.
        config(['cache.default' => 'array']);
    }

    protected function tearDown(): void
    {
        Cache::forget('gocardless.access_token');
        parent::tearDown();
    }

    private function client(): GoCardlessClient
    {
        return new GoCardlessClient('secret-id', 'secret-key');
    }

    private function fakeToken(): array
    {
        return [
            'bankaccountdata.gocardless.com/api/v2/token/new/' => Http::response([
                'access' => 'ACCESS_TOKEN',
                'access_expires' => 86400,
            ]),
        ];
    }

    public function test_lists_institutions_for_a_country(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'bankaccountdata.gocardless.com/api/v2/institutions/*' => Http::response([
                ['id' => 'REVOLUT_REVOGB21', 'name' => 'Revolut'],
                ['id' => 'N26_NTSBDEB1', 'name' => 'N26'],
            ]),
        ]));

        $institutions = $this->client()->institutions('IT');

        $this->assertCount(2, $institutions);
        $this->assertSame('Revolut', $institutions[0]['name']);
    }

    public function test_creates_a_requisition_and_returns_the_consent_link(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'bankaccountdata.gocardless.com/api/v2/requisitions/' => Http::response([
                'id' => 'req-123',
                'link' => 'https://ob.gocardless.com/consent/abc',
            ]),
        ]));

        $result = $this->client()->createRequisition('SANDBOXFINANCE_SFIN0000', 'http://localhost:8080/settings');

        $this->assertSame('req-123', $result['requisition_id']);
        $this->assertSame('https://ob.gocardless.com/consent/abc', $result['link']);
    }

    public function test_lists_accounts_from_a_requisition(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'bankaccountdata.gocardless.com/api/v2/requisitions/req-123/' => Http::response([
                'id' => 'req-123',
                'status' => 'LN',
                'accounts' => ['acc-1', 'acc-2'],
            ]),
        ]));

        $accounts = $this->client()->requisitionAccounts('req-123');

        $this->assertSame(['acc-1', 'acc-2'], $accounts);
    }

    public function test_reads_an_account_balance(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'bankaccountdata.gocardless.com/api/v2/accounts/acc-1/balances/' => Http::response([
                'balances' => [
                    ['balanceAmount' => ['amount' => '1234.56', 'currency' => 'EUR'], 'balanceType' => 'expected'],
                ],
            ]),
        ]));

        $balance = $this->client()->accountBalance('acc-1');

        $this->assertEqualsWithDelta(1234.56, $balance['amount'], 0.001);
        $this->assertSame('EUR', $balance['currency']);
    }

    public function test_balance_returns_null_on_unexpected_shape(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'bankaccountdata.gocardless.com/api/v2/accounts/acc-1/balances/' => Http::response(['balances' => [['wrong' => true]]]),
        ]));

        $this->assertNull($this->client()->accountBalance('acc-1'));
    }

    public function test_returns_empty_when_token_request_fails(): void
    {
        Http::fake([
            'bankaccountdata.gocardless.com/api/v2/token/new/' => Http::response('', 401),
        ]);

        $this->assertSame([], $this->client()->institutions('IT'));
    }

    public function test_balance_returns_null_when_account_request_fails(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'bankaccountdata.gocardless.com/api/v2/accounts/*' => Http::response('', 500),
        ]));

        $this->assertNull($this->client()->accountBalance('acc-1'));
    }
}
