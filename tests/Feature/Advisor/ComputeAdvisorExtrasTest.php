<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Actions\Advisor\ComputeAdvisorExtras;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ComputeAdvisorExtrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_contribution_is_null_without_savings_plan_transactions(): void
    {
        $asset = Asset::factory()->create();
        // A one-off (single) buy must not count as a recurring contribution.
        Transaction::factory()->create([
            'asset_id' => $asset->id,
            'source' => Transaction::SOURCE_SINGLE,
            'shares' => 1, 'price_per_share' => 100, 'date' => Carbon::today()->format('Y-m-d'),
        ]);

        $extras = app(ComputeAdvisorExtras::class)->run();

        $this->assertNull($extras['contribution']);
    }

    public function test_contribution_averages_savings_plan_buys_over_the_window(): void
    {
        $asset = Asset::factory()->create();
        // Two PAC buys of 300 (shares*price) within the window → 600 total.
        // Averaged over the 6-month window = 100/month.
        Transaction::factory()->create([
            'asset_id' => $asset->id,
            'source' => Transaction::SOURCE_SAVINGS_PLAN,
            'shares' => 3, 'price_per_share' => 100, 'fees' => null,
            'date' => Carbon::today()->startOfMonth()->format('Y-m-d'),
        ]);
        Transaction::factory()->create([
            'asset_id' => $asset->id,
            'source' => Transaction::SOURCE_SAVINGS_PLAN,
            'shares' => 3, 'price_per_share' => 100, 'fees' => null,
            'date' => Carbon::today()->startOfMonth()->subMonth()->format('Y-m-d'),
        ]);

        $extras = app(ComputeAdvisorExtras::class)->run();

        $this->assertSame(100.0, $extras['contribution']['monthly_avg']);
        $this->assertSame(6, $extras['contribution']['months']);
    }

    public function test_costs_are_null_when_no_asset_has_a_ter(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create([
            'category_id' => $cat->id, 'value' => 1000, 'expense_ratio' => null,
            'date' => Carbon::today()->format('Y-m-01'),
        ]);

        $this->assertNull(app(ComputeAdvisorExtras::class)->run()['costs']);
    }

    public function test_costs_are_value_weighted_across_assets_with_a_ter(): void
    {
        $cat = Category::factory()->create();
        $month = Carbon::today()->format('Y-m-01');
        // 1000 @ 0.20% and 3000 @ 0.60% → annual cost 2 + 18 = 20 on 4000 covered
        // → weighted TER = 20/4000 = 0.50%.
        Asset::factory()->create(['category_id' => $cat->id, 'name' => 'A', 'value' => 1000, 'expense_ratio' => 0.20, 'date' => $month]);
        Asset::factory()->create(['category_id' => $cat->id, 'name' => 'B', 'value' => 3000, 'expense_ratio' => 0.60, 'date' => $month]);

        $costs = app(ComputeAdvisorExtras::class)->run()['costs'];

        $this->assertSame(0.5, $costs['weighted_ter_pct']);
        $this->assertSame(20.0, $costs['annual_cost']);
        $this->assertSame(4000.0, $costs['covered_value']);
    }
}
