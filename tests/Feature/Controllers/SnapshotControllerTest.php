<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Jobs\BackupDatabase;
use App\Models\Asset;
use App\Models\BankConnection;
use App\Models\Category;
use App\Models\Snapshot;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SnapshotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        // Enable Banking unconfigured by default: no live values to refresh,
        // so a today-dated snapshot makes no outbound call.
        config(['services.enable_banking.application_id' => '', 'services.enable_banking.private_key_path' => '']);
    }

    public function test_creates_a_snapshot_with_correct_total(): void
    {
        $category = Category::factory()->create();
        Asset::factory()->create(['category_id' => $category->id, 'value' => 1000.00, 'date' => '2025-03-01']);
        Asset::factory()->create(['category_id' => $category->id, 'value' => 500.00, 'date' => '2025-03-01']);

        $this->post('/snapshots', ['date' => '2025-03-01'])->assertRedirect();

        $this->assertDatabaseHas('snapshots', ['date' => '2025-03-01', 'total_value' => 1500.00]);
    }

    public function test_upserts_without_creating_duplicates(): void
    {
        $category = Category::factory()->create();
        Asset::factory()->create(['category_id' => $category->id, 'value' => 1000.00, 'date' => '2025-03-01']);

        $this->post('/snapshots', ['date' => '2025-03-01']);

        Asset::factory()->create(['category_id' => $category->id, 'value' => 200.00, 'date' => '2025-03-01']);

        $this->post('/snapshots', ['date' => '2025-03-01'])->assertRedirect();

        $this->assertSame(1, Snapshot::count());
        $this->assertDatabaseHas('snapshots', ['date' => '2025-03-01', 'total_value' => 1200.00]);
    }

    public function test_a_today_snapshot_refreshes_bank_balances_first(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $keyPath = tempnam(sys_get_temp_dir(), 'eb_snap_key');
        file_put_contents($keyPath, $pem);
        config([
            'cache.default' => 'array',
            'services.enable_banking.application_id' => 'app-uuid',
            'services.enable_banking.private_key_path' => $keyPath,
        ]);

        $category = Category::factory()->create();
        // Stale stored value; the bank really has 4321.00 right now.
        Asset::factory()->create([
            'category_id' => $category->id, 'name' => 'Conto', 'value' => 100.00,
            'date' => now()->format('Y-m-01'),
        ]);
        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 'st', 'session_id' => 's',
            'status' => BankConnection::STATUS_ACTIVE, 'valid_until' => now()->addDays(30),
        ]);
        $connection->accounts()->create(['uid' => 'acc-1', 'linked_name' => 'Conto', 'linked_category_id' => $category->id]);

        Http::fake([
            'api.enablebanking.com/accounts/acc-1/balances' => Http::response([
                'balances' => [['balance_amount' => ['amount' => '4321.00', 'currency' => 'EUR']]],
            ]),
        ]);

        $this->post('/snapshots', ['date' => now()->toDateString()])->assertRedirect();

        // The snapshot captured the freshly-synced balance, not the stale 100.
        $this->assertDatabaseHas('snapshots', ['date' => now()->toDateString(), 'total_value' => 4321.00]);

        @unlink($keyPath);
    }

    public function test_saving_a_snapshot_queues_a_backup(): void
    {
        Queue::fake();

        $category = Category::factory()->create();
        Asset::factory()->create(['category_id' => $category->id, 'value' => 1000.00, 'date' => '2025-03-01']);

        $this->post('/snapshots', ['date' => '2025-03-01'])->assertRedirect();

        Queue::assertPushed(BackupDatabase::class);
    }
}
