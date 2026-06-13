<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Asset;
use App\Models\Transaction;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnlinkTransactionsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_unlinking_removes_transactions_and_keeps_the_quantity(): void
    {
        $asset = Asset::factory()->create(['ticker' => 'SWDA', 'quantity' => 153.045583]);
        Transaction::factory()->count(3)->for($asset)->create();

        $this->assertTrue($asset->isTransactionManaged());

        $this->delete("/assets/{$asset->id}/transactions")->assertRedirect();

        $fresh = $asset->fresh();
        $this->assertFalse($fresh->isTransactionManaged());                 // no longer managed
        $this->assertSame(153.045583, $fresh->quantity);                    // last quantity kept
        $this->assertSame(0, $fresh->transactions()->count());              // transactions gone
    }

    public function test_after_unlink_quantity_is_editable_again(): void
    {
        $asset = Asset::factory()->create(['ticker' => 'SWDA', 'quantity' => 10]);
        Transaction::factory()->for($asset)->create();

        $this->delete("/assets/{$asset->id}/transactions")->assertRedirect();

        // Manual edit is now allowed (not stripped, since no transactions remain).
        $this->put("/assets/{$asset->id}", ['quantity' => 42])->assertRedirect();
        $this->assertSame(42.0, $asset->fresh()->quantity);
    }
}
