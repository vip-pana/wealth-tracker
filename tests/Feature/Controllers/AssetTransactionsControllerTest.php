<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Asset;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTransactionsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_transactions_and_position_summary(): void
    {
        $asset = Asset::factory()->create(['ticker' => 'SWDA']);
        Transaction::factory()->for($asset)->create(['type' => Transaction::TYPE_BUY, 'shares' => 10, 'price_per_share' => 100, 'date' => '2025-01-01']);
        Transaction::factory()->for($asset)->create(['type' => Transaction::TYPE_BUY, 'shares' => 10, 'price_per_share' => 120, 'date' => '2025-02-01']);

        $response = $this->getJson("/assets/{$asset->id}/transactions");

        $response->assertOk()->assertJsonCount(2, 'transactions');

        $this->assertEqualsWithDelta(20.0, (float) $response->json('position.shares'), 0.001);
        $this->assertEqualsWithDelta(110.0, (float) $response->json('position.average_cost'), 0.001);
        $this->assertEqualsWithDelta(2200.0, (float) $response->json('position.cost_basis'), 0.001);

        // Newest first.
        $this->assertSame('2025-02-01', $response->json('transactions.0.date'));
    }

    public function test_position_is_flat_for_an_asset_without_transactions(): void
    {
        $asset = Asset::factory()->create();

        $response = $this->getJson("/assets/{$asset->id}/transactions")
            ->assertOk()
            ->assertJsonCount(0, 'transactions');

        $this->assertEqualsWithDelta(0.0, (float) $response->json('position.shares'), 0.001);
    }
}
