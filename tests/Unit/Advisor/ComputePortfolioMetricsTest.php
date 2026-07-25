<?php

declare(strict_types=1);

namespace Tests\Unit\Advisor;

use App\Actions\Advisor\ComputePortfolioMetrics;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Snapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ComputePortfolioMetricsTest extends TestCase
{
    /** @param array<int, string> $names */
    private function categories(array $names): Collection
    {
        return collect($names)->map(function (string $name, int $id): Category {
            $category = new Category(['name' => $name]);
            $category->id = $id;

            return $category;
        })->values();
    }

    /**
     * @param  array<int, float>  $valuesByCategory  category_id => value
     */
    private function snapshot(int $id, string $date, array $valuesByCategory): Snapshot
    {
        $snapshot = new Snapshot([
            'date' => $date,
            'total_value' => array_sum($valuesByCategory),
        ]);
        $snapshot->id = $id;

        $values = collect($valuesByCategory)->map(fn (float $value, int $categoryId): SnapshotCategoryValue => new SnapshotCategoryValue([
            'category_id' => $categoryId,
            'value' => $value,
        ]))->values();

        $snapshot->setRelation('categoryValues', $values);

        return $snapshot;
    }

    public function test_returns_no_data_flag_when_empty(): void
    {
        $result = (new ComputePortfolioMetrics)->run(collect(), $this->categories([]), null);

        $this->assertFalse($result['hasData']);
    }

    public function test_computes_allocation_shares_sorted_largest_first(): void
    {
        $categories = $this->categories([1 => 'Liquidità', 2 => 'ETF']);
        $snapshots = collect([
            $this->snapshot(1, '2025-01-01', [1 => 2500.0, 2 => 7500.0]),
        ]);

        $result = (new ComputePortfolioMetrics)->run($snapshots, $categories, null);

        $this->assertTrue($result['hasData']);
        $this->assertSame(10000.0, $result['totalNetWorth']);
        $this->assertSame('ETF', $result['allocation'][0]['name']);
        $this->assertSame(75.0, $result['allocation'][0]['share_pct']);
        $this->assertSame(25.0, $result['allocation'][1]['share_pct']);
    }

    public function test_concentration_uses_hhi_and_names_top_category(): void
    {
        $categories = $this->categories([1 => 'Liquidità', 2 => 'ETF']);
        $snapshots = collect([
            $this->snapshot(1, '2025-01-01', [1 => 2000.0, 2 => 8000.0]),
        ]);

        $result = (new ComputePortfolioMetrics)->run($snapshots, $categories, null);

        // 80^2 + 20^2 = 6800
        $this->assertSame(6800.0, $result['concentration']['hhi']);
        $this->assertSame('ETF', $result['concentration']['top_category']);
        $this->assertSame(80.0, $result['concentration']['top_share_pct']);
    }

    public function test_liquidity_isolates_the_cash_category(): void
    {
        $categories = $this->categories([1 => 'Liquidità', 2 => 'ETF']);
        $snapshots = collect([
            $this->snapshot(1, '2025-01-01', [1 => 3000.0, 2 => 7000.0]),
        ]);

        $result = (new ComputePortfolioMetrics)->run($snapshots, $categories, null);

        $this->assertSame(3000.0, $result['liquidity']['value']);
        $this->assertSame(30.0, $result['liquidity']['share_pct']);
    }

    public function test_allocation_drift_reports_point_change_vs_first_month(): void
    {
        $categories = $this->categories([1 => 'Liquidità', 2 => 'ETF']);
        $snapshots = collect([
            $this->snapshot(1, '2025-01-01', [1 => 5000.0, 2 => 5000.0]),   // 50 / 50
            $this->snapshot(2, '2025-02-01', [1 => 2000.0, 2 => 8000.0]),   // 20 / 80
        ]);

        $result = (new ComputePortfolioMetrics)->run($snapshots, $categories, null);

        $byName = collect($result['allocationDrift'])->keyBy('name');
        $this->assertSame(30.0, $byName['ETF']['delta_pp']);       // 50 -> 80
        $this->assertSame(-30.0, $byName['Liquidità']['delta_pp']); // 50 -> 20
    }

    public function test_volatility_is_null_until_two_returns_exist(): void
    {
        $categories = $this->categories([1 => 'ETF']);
        $snapshots = collect([
            $this->snapshot(1, '2025-01-01', [1 => 1000.0]),
            $this->snapshot(2, '2025-02-01', [1 => 1100.0]),
        ]);

        $result = (new ComputePortfolioMetrics)->run($snapshots, $categories, null);

        $this->assertNull($result['volatility']['monthly_stddev_pct']);
    }

    public function test_volatility_reports_stddev_and_extremes(): void
    {
        $categories = $this->categories([1 => 'ETF']);
        $snapshots = collect([
            $this->snapshot(1, '2025-01-01', [1 => 1000.0]),
            $this->snapshot(2, '2025-02-01', [1 => 1100.0]),  // +10%
            $this->snapshot(3, '2025-03-01', [1 => 990.0]),   // -10%
        ]);

        $result = (new ComputePortfolioMetrics)->run($snapshots, $categories, null);

        $this->assertSame(10.0, $result['volatility']['best_month_pct']);
        $this->assertSame(-10.0, $result['volatility']['worst_month_pct']);
        $this->assertSame(10.0, $result['volatility']['monthly_stddev_pct']);
    }

    public function test_goal_eta_flags_already_reached(): void
    {
        $categories = $this->categories([1 => 'ETF']);
        $snapshots = collect([
            $this->snapshot(1, '2025-01-01', [1 => 9000.0]),
            $this->snapshot(2, '2025-02-01', [1 => 11000.0]),
        ]);
        $goal = new Goal(['target_value' => 10000.0]);

        $result = (new ComputePortfolioMetrics)->run($snapshots, $categories, $goal);

        $this->assertTrue($result['goalEta']['reached']);
    }

    public function test_goal_eta_projects_months_from_regression_slope(): void
    {
        $categories = $this->categories([1 => 'ETF']);
        $snapshots = collect([
            $this->snapshot(1, '2025-01-01', [1 => 1000.0]),
            $this->snapshot(2, '2025-02-01', [1 => 2000.0]),  // +1000 over 31 days
        ]);
        $goal = new Goal(['target_value' => 5000.0]);

        $result = (new ComputePortfolioMetrics)->run($snapshots, $categories, $goal);

        $this->assertFalse($result['goalEta']['reached']);
        // slope = (2000-1000)/31 per day * 30.44 days ≈ 981.94 per month
        $this->assertSame(981.94, $result['goalEta']['avg_monthly_gain']);
        $this->assertSame(4, $result['goalEta']['months_to_goal']); // ceil((5000-2000)/981.94)
        $this->assertSame('2025-06-01', $result['goalEta']['projected_date']);
        // Only two months tracked → estimate flagged as low confidence.
        $this->assertTrue($result['goalEta']['low_confidence']);
    }

    public function test_goal_eta_drops_low_confidence_flag_with_enough_months(): void
    {
        $categories = $this->categories([1 => 'ETF']);
        $snapshots = collect([
            $this->snapshot(1, '2025-01-01', [1 => 1000.0]),
            $this->snapshot(2, '2025-02-01', [1 => 2000.0]),
            $this->snapshot(3, '2025-03-01', [1 => 3000.0]),
            $this->snapshot(4, '2025-04-01', [1 => 4000.0]),
        ]);
        $goal = new Goal(['target_value' => 50000.0]);

        $result = (new ComputePortfolioMetrics)->run($snapshots, $categories, $goal);

        $this->assertFalse($result['goalEta']['low_confidence']);
    }
}
