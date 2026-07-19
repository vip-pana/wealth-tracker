<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\BankConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportBankTransactionsCommandTest extends TestCase
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

    public function test_imports_and_reports_a_summary(): void
    {
        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 'st', 'session_id' => 's',
            'status' => BankConnection::STATUS_ACTIVE, 'valid_until' => Carbon::now()->addDays(30),
        ]);
        $connection->accounts()->create(['uid' => 'acc-1', 'iban' => 'IT1']);

        Http::fake([
            'api.enablebanking.com/accounts/acc-1/transactions*' => Http::response([
                'transactions' => [[
                    'entry_reference' => 'tx-1',
                    'transaction_amount' => ['amount' => '12.50', 'currency' => 'EUR'],
                    'credit_debit_indicator' => 'DBIT',
                    'booking_date' => '2026-07-01',
                ]],
                'continuation_key' => null,
            ]),
        ]);

        $this->artisan('bank:import-transactions')
            ->expectsOutputToContain('Imported 1 transaction(s) across 1 account(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('bank_transactions', ['external_id' => 'tx-1']);
    }
}
