<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_stores_a_category(): void
    {
        $this->post('/categories', [
            'name' => 'Stocks',
            'color' => '#FF0000',
            'sort_order' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('categories', ['name' => 'Stocks']);
    }

    public function test_fails_to_store_without_name(): void
    {
        $this->post('/categories', [
            'color' => '#FF0000',
        ])->assertRedirect()->assertSessionHasErrors('name');
    }

    public function test_updates_a_category(): void
    {
        $category = Category::factory()->create(['name' => 'OldName']);

        $this->put("/categories/{$category->id}", [
            'name' => 'NewName',
            'color' => '#00FF00',
        ])->assertRedirect();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'NewName']);
    }

    public function test_destroys_a_category_with_no_assets(): void
    {
        $category = Category::factory()->create();

        $this->delete("/categories/{$category->id}")->assertRedirect();

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_refuses_to_destroy_a_category_with_assets(): void
    {
        $category = Category::factory()->create();
        Asset::factory()->create(['category_id' => $category->id]);

        $this->delete("/categories/{$category->id}")->assertRedirect()->assertSessionHasErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
