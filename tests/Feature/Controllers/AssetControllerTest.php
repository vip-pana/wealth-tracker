<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Asset;
use App\Models\BankConnection;
use App\Models\Category;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /** Create an active bank link to the logical asset (name + category). */
    private function activeLink(string $name, int $categoryId): void
    {
        $connection = BankConnection::create([
            'aspsp_name' => 'Revolut', 'aspsp_country' => 'IT', 'state' => 'st-'.$name,
            'session_id' => 'sess', 'status' => BankConnection::STATUS_ACTIVE,
            'valid_until' => now()->addDays(30),
        ]);
        $connection->accounts()->create([
            'uid' => 'acc-'.$name, 'linked_name' => $name, 'linked_category_id' => $categoryId,
        ]);
    }

    public function test_stores_an_asset(): void
    {
        $category = Category::factory()->create();

        $this->post('/assets', [
            'category_id' => $category->id,
            'name' => 'ETF',
            'value' => 1000.00,
            'date' => '2025-01-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['name' => 'ETF', 'category_id' => $category->id]);
    }

    public function test_fails_to_store_without_category_id(): void
    {
        $this->post('/assets', [
            'name' => 'ETF',
            'value' => 1000.00,
            'date' => '2025-01-01',
        ])->assertRedirect()->assertSessionHasErrors('category_id');
    }

    public function test_updates_an_asset(): void
    {
        $asset = Asset::factory()->create(['value' => 500.00]);

        $this->put("/assets/{$asset->id}", [
            'value' => 1500.00,
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'value' => 1500.00]);
    }

    public function test_destroys_an_asset(): void
    {
        $asset = Asset::factory()->create();

        $this->delete("/assets/{$asset->id}")->assertRedirect();

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_destroy_flashes_an_undo_url(): void
    {
        $asset = Asset::factory()->create();

        $this->delete("/assets/{$asset->id}")
            ->assertRedirect()
            ->assertSessionHas('undo', route('assets.restore', $asset->id, absolute: false));
    }

    public function test_restores_a_soft_deleted_asset(): void
    {
        $asset = Asset::factory()->create();
        $asset->delete();
        $this->assertSoftDeleted('assets', ['id' => $asset->id]);

        $this->post("/assets/{$asset->id}/restore")->assertRedirect();

        $this->assertNotSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_cannot_create_an_asset_colliding_with_an_active_bank_link(): void
    {
        $category = Category::factory()->create();
        $this->activeLink('Conto', $category->id);

        $this->post('/assets', [
            'category_id' => $category->id,
            'name' => 'Conto',
            'value' => 1000.00,
            'date' => '2025-01-01',
        ])->assertRedirect()->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('assets', ['name' => 'Conto', 'value' => 1000.00]);
    }

    public function test_update_cannot_change_identity_or_value_of_a_bank_linked_asset(): void
    {
        $category = Category::factory()->create();
        $other = Category::factory()->create();
        $this->activeLink('Conto', $category->id);
        $asset = Asset::factory()->create([
            'name' => 'Conto', 'category_id' => $category->id, 'value' => 500.00,
        ]);

        $this->put("/assets/{$asset->id}", [
            'name' => 'Rinominato',
            'category_id' => $other->id,
            'value' => 9999.00,
            'notes' => 'una nota',
        ])->assertRedirect();

        // Identity and value untouched; notes (a free field) applied.
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id, 'name' => 'Conto', 'category_id' => $category->id,
            'value' => 500.00, 'notes' => 'una nota',
        ]);
    }

    public function test_cannot_rename_a_free_asset_onto_an_active_bank_link(): void
    {
        $category = Category::factory()->create();
        $this->activeLink('Conto', $category->id);
        $asset = Asset::factory()->create([
            'name' => 'Contanti', 'category_id' => $category->id, 'value' => 200.00,
        ]);

        $this->put("/assets/{$asset->id}", [
            'name' => 'Conto',
        ])->assertRedirect()->assertSessionHasErrors('name');

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'name' => 'Contanti']);
    }
}
