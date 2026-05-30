<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Asset;
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
}
