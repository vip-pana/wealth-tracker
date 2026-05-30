<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\FetchAvailableMonths;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FetchAvailableMonthsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_distinct_months_normalised_to_the_first(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create(['category_id' => $cat->id, 'date' => '2026-01-15']);
        Asset::factory()->create(['category_id' => $cat->id, 'date' => '2026-02-20']);

        $this->assertSame(['2026-02-01', '2026-01-01'], app(FetchAvailableMonths::class)->run());
    }

    public function test_collapses_multiple_assets_in_the_same_month(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create(['category_id' => $cat->id, 'date' => '2026-02-01']);
        Asset::factory()->create(['category_id' => $cat->id, 'date' => '2026-02-10']);
        Asset::factory()->create(['category_id' => $cat->id, 'date' => '2026-02-28']);

        $this->assertSame(['2026-02-01'], app(FetchAvailableMonths::class)->run());
    }

    public function test_sorts_descending_across_year_boundaries(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create(['category_id' => $cat->id, 'date' => '2025-12-31']);
        Asset::factory()->create(['category_id' => $cat->id, 'date' => '2026-01-01']);

        $this->assertSame(['2026-01-01', '2025-12-01'], app(FetchAvailableMonths::class)->run());
    }

    public function test_returns_empty_when_there_are_no_assets(): void
    {
        $this->assertSame([], app(FetchAvailableMonths::class)->run());
    }
}
