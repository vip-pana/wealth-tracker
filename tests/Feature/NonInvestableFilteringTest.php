<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Snapshots\StoreSnapshot;
use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Goal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NonInvestableFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_keeps_buffer_in_net_worth_but_out_of_the_investment_view(): void
    {
        $etf = Category::factory()->create(['name' => 'ETF', 'macro_category' => MacroCategory::ETF, 'investable' => true]);
        $buffer = Category::factory()->create(['name' => 'Fondo emergenza', 'macro_category' => MacroCategory::Liquidita, 'investable' => false]);

        Asset::factory()->create(['category_id' => $etf->id, 'value' => 10000, 'date' => '2026-01-31']);
        Asset::factory()->create(['category_id' => $buffer->id, 'value' => 3000, 'date' => '2026-01-31']);

        app(StoreSnapshot::class)->run('2026-01-31');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                // Buffer stays in total net worth…
                ->where('totalNetWorth', 13000)
                // …but is carved out of the investable view and shown separately.
                ->where('investableNetWorth', 10000)
                ->where('bufferNetWorth', 3000)
                ->where('hasBuffer', true)
                // and it's dropped from the investment categories list.
                ->has('categories', 1)
                ->where('categories.0.name', 'ETF')
            );
    }

    public function test_goal_current_net_worth_excludes_the_buffer(): void
    {
        $etf = Category::factory()->create(['name' => 'ETF', 'macro_category' => MacroCategory::ETF, 'investable' => true]);
        $buffer = Category::factory()->create(['name' => 'Fondo emergenza', 'macro_category' => MacroCategory::Liquidita, 'investable' => false]);

        Asset::factory()->create(['category_id' => $etf->id, 'value' => 10000, 'date' => '2026-01-31']);
        Asset::factory()->create(['category_id' => $buffer->id, 'value' => 3000, 'date' => '2026-01-31']);
        app(StoreSnapshot::class)->run('2026-01-31');

        Goal::create(['name' => 'G', 'target_value' => 100000, 'target_date' => '2099-01-01']);

        $this->get('/goal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Goal')
                // Only the investable 10.000 counts toward the goal, not the 13.000 total.
                ->where('currentNetWorth', 10000)
            );
    }
}
