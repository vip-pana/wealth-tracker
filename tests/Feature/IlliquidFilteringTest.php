<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Snapshots\StoreSnapshot;
use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IlliquidFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_separate_liquid_and_illiquid_totals(): void
    {
        $etf = Category::factory()->create(['macro_category' => MacroCategory::ETF]);
        $pension = Category::factory()->create(['macro_category' => MacroCategory::FondoPensione]);

        Asset::factory()->create(['category_id' => $etf->id, 'value' => 10000, 'date' => '2026-01-31']);
        Asset::factory()->create(['category_id' => $pension->id, 'value' => 5000, 'date' => '2026-01-31']);

        app(StoreSnapshot::class)->run('2026-01-31');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('totalNetWorth', 15000)
                ->where('liquidNetWorth', 10000)
                ->where('illiquidNetWorth', 5000)
                ->where('hasIlliquid', true)
            );
    }

    public function test_dashboard_excludes_illiquid_categories_from_categories_list(): void
    {
        $etf = Category::factory()->create(['name' => 'My ETF', 'macro_category' => MacroCategory::ETF]);
        $pension = Category::factory()->create(['name' => 'My Pension', 'macro_category' => MacroCategory::FondoPensione]);

        Asset::factory()->create(['category_id' => $etf->id, 'value' => 100, 'date' => '2026-01-31']);
        Asset::factory()->create(['category_id' => $pension->id, 'value' => 100, 'date' => '2026-01-31']);

        app(StoreSnapshot::class)->run('2026-01-31');

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('categories', 1)
                ->where('categories.0.name', 'My ETF')
            );
    }

    public function test_input_excludes_illiquid_categories_and_assets(): void
    {
        $etf = Category::factory()->create(['name' => 'My ETF', 'macro_category' => MacroCategory::ETF]);
        $pension = Category::factory()->create(['name' => 'My Pension', 'macro_category' => MacroCategory::FondoPensione]);

        Asset::factory()->create(['category_id' => $etf->id, 'name' => 'ETF Asset', 'value' => 1000, 'date' => '2026-01-31']);
        Asset::factory()->create(['category_id' => $pension->id, 'name' => 'Pension Asset', 'value' => 5000, 'date' => '2026-01-31']);

        $this->get('/input?month=2026-01-31')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('InputData')
                ->has('categories', 1)
                ->where('categories.0.name', 'My ETF')
                ->has('assets', 1)
                ->where('assets.0.name', 'ETF Asset')
            );
    }
}
