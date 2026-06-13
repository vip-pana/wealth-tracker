<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_a_transaction_and_links_it_to_its_asset(): void
    {
        $asset = Asset::factory()->create();

        $tx = Transaction::factory()->for($asset)->create([
            'type' => Transaction::TYPE_BUY,
            'shares' => 3.069249,
            'price_per_share' => 104.26,
        ]);

        $this->assertSame(Transaction::TYPE_BUY, $tx->type);
        $this->assertSame(3.069249, $tx->shares);
        $this->assertTrue($tx->asset->is($asset));

        $this->assertDatabaseHas('transactions', [
            'id' => $tx->id,
            'asset_id' => $asset->id,
            'type' => 'buy',
        ]);
    }

    public function test_exposes_transactions_through_the_asset_relation(): void
    {
        $asset = Asset::factory()->create();
        Transaction::factory()->count(2)->for($asset)->create();
        Transaction::factory()->for($asset)->sell()->create();

        $this->assertCount(3, $asset->transactions);
        $this->assertCount(1, $asset->transactions->where('type', Transaction::TYPE_SELL));
    }

    public function test_soft_deletes_transactions(): void
    {
        $tx = Transaction::factory()->create();
        $tx->delete();

        $this->assertSoftDeleted('transactions', ['id' => $tx->id]);
    }

    public function test_external_id_is_unique(): void
    {
        Transaction::factory()->create(['external_id' => 'scalable-abc']);

        $this->expectException(QueryException::class);
        Transaction::factory()->create(['external_id' => 'scalable-abc']);
    }
}
