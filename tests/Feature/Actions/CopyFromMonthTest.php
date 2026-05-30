<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopyFromMonthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_copies_every_asset_from_the_source_month_to_the_target(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create(['category_id' => $cat->id, 'name' => 'A', 'value' => 100, 'date' => '2026-01-01']);
        Asset::factory()->create(['category_id' => $cat->id, 'name' => 'B', 'value' => 200, 'date' => '2026-01-01']);

        $this->post('/assets/copy-from-month', [
            'source_date' => '2026-01-01',
            'month' => '2026-02-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['name' => 'A', 'date' => '2026-02-01', 'value' => 100]);
        $this->assertDatabaseHas('assets', ['name' => 'B', 'date' => '2026-02-01', 'value' => 200]);
        // Originals are untouched.
        $this->assertSame(2, Asset::whereDate('date', '2026-01-01')->count());
        $this->assertSame(2, Asset::whereDate('date', '2026-02-01')->count());
    }

    public function test_preserves_ticker_and_quantity_when_copying(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create([
            'category_id' => $cat->id,
            'name' => 'BTC',
            'ticker' => 'BTC',
            'quantity' => 0.5,
            'value' => 30000,
            'date' => '2026-01-01',
        ]);

        $this->post('/assets/copy-from-month', [
            'source_date' => '2026-01-01',
            'month' => '2026-02-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', [
            'name' => 'BTC',
            'ticker' => 'BTC',
            'quantity' => 0.5,
            'date' => '2026-02-01',
        ]);
    }

    public function test_copying_an_empty_month_creates_nothing(): void
    {
        Category::factory()->create();

        $this->post('/assets/copy-from-month', [
            'source_date' => '2026-01-01',
            'month' => '2026-02-01',
        ])->assertRedirect();

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_source_date_is_required(): void
    {
        $this->post('/assets/copy-from-month', [
            'month' => '2026-02-01',
        ])->assertSessionHasErrors('source_date');
    }
}
