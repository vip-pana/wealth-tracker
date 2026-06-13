<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Transactions\SyncAssetQuantity;
use App\Models\Asset;
use App\Models\Transaction;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionManagedAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_sync_sets_quantity_from_transactions(): void
    {
        $asset = Asset::factory()->create(['ticker' => 'SWDA', 'quantity' => 0]);
        Transaction::factory()->for($asset)->create(['type' => Transaction::TYPE_BUY, 'shares' => 10, 'price_per_share' => 100, 'date' => '2025-01-01']);
        Transaction::factory()->for($asset)->create(['type' => Transaction::TYPE_BUY, 'shares' => 5, 'price_per_share' => 120, 'date' => '2025-02-01']);
        Transaction::factory()->for($asset)->sell()->create(['shares' => 3, 'price_per_share' => 130, 'date' => '2025-03-01']);

        app(SyncAssetQuantity::class)->run($asset);

        $this->assertSame(12.0, $asset->fresh()->quantity); // 10 + 5 - 3
    }

    public function test_sync_leaves_a_transactionless_asset_untouched(): void
    {
        $asset = Asset::factory()->create(['quantity' => 7.5]);

        app(SyncAssetQuantity::class)->run($asset);

        $this->assertSame(7.5, $asset->fresh()->quantity);
    }

    public function test_is_transaction_managed_reflects_having_transactions(): void
    {
        $asset = Asset::factory()->create();
        $this->assertFalse($asset->isTransactionManaged());

        Transaction::factory()->for($asset)->create();
        $this->assertTrue($asset->fresh()->isTransactionManaged());
    }

    public function test_update_ignores_manual_quantity_on_a_transaction_managed_asset(): void
    {
        $asset = Asset::factory()->create(['ticker' => 'SWDA', 'quantity' => 12]);
        Transaction::factory()->for($asset)->create(['shares' => 12, 'price_per_share' => 100]);

        $this->put("/assets/{$asset->id}", [
            'quantity' => 999,
            'notes' => 'updated note',
        ])->assertRedirect();

        $fresh = $asset->fresh();
        $this->assertSame(12.0, $fresh->quantity);          // quantity ignored
        $this->assertSame('updated note', $fresh->notes);   // free field still applied
    }

    public function test_update_still_allows_quantity_on_a_manual_asset(): void
    {
        $asset = Asset::factory()->create(['ticker' => 'SWDA', 'quantity' => 5]);

        $this->put("/assets/{$asset->id}", [
            'quantity' => 8,
        ])->assertRedirect();

        $this->assertSame(8.0, $asset->fresh()->quantity);
    }
}
