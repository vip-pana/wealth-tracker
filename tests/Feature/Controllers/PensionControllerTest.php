<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PensionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function pensionCategory(): Category
    {
        return Category::factory()->create([
            'name' => 'Fondo Pensione Personale',
            'macro_category' => MacroCategory::FondoPensione,
        ]);
    }

    public function test_stores_pension_entry_with_year_converted_to_dec_31(): void
    {
        $category = $this->pensionCategory();

        $this->post('/pension', [
            'category_id' => $category->id,
            'name' => 'Report 2026',
            'value' => 15000,
            'year' => 2026,
            'notes' => 'Estratto annuale',
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', [
            'category_id' => $category->id,
            'name' => 'Report 2026',
            'value' => 15000.00,
            'date' => '2026-12-31',
            'notes' => 'Estratto annuale',
        ]);
    }

    public function test_rejects_storing_into_non_pension_category(): void
    {
        $category = Category::factory()->create(['macro_category' => MacroCategory::ETF]);

        $this->post('/pension', [
            'category_id' => $category->id,
            'name' => 'Bad',
            'value' => 100,
            'year' => 2026,
        ])->assertRedirect()->assertSessionHasErrors('category_id');

        $this->assertDatabaseMissing('assets', ['name' => 'Bad']);
    }

    public function test_rejects_storing_into_category_with_null_macro(): void
    {
        $category = Category::factory()->create(['macro_category' => null]);

        $this->post('/pension', [
            'category_id' => $category->id,
            'name' => 'Bad',
            'value' => 100,
            'year' => 2026,
        ])->assertRedirect()->assertSessionHasErrors('category_id');
    }

    public function test_updates_pension_entry_year_and_value(): void
    {
        $category = $this->pensionCategory();
        $asset = Asset::factory()->create([
            'category_id' => $category->id,
            'value' => 10000,
            'date' => '2025-12-31',
        ]);

        $this->put("/pension/{$asset->id}", [
            'value' => 18000,
            'year' => 2026,
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'value' => 18000.00,
            'date' => '2026-12-31',
        ]);
    }

    public function test_destroys_pension_entry(): void
    {
        $category = $this->pensionCategory();
        $asset = Asset::factory()->create(['category_id' => $category->id]);

        $this->delete("/pension/{$asset->id}")->assertRedirect();

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_update_without_year_keeps_existing_date(): void
    {
        $category = $this->pensionCategory();
        $asset = Asset::factory()->create([
            'category_id' => $category->id,
            'value' => 10000,
            'date' => '2025-12-31',
        ]);

        $this->put("/pension/{$asset->id}", [
            'value' => 12000,
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'value' => 12000.00,
            'date' => '2025-12-31',
        ]);
    }
}
