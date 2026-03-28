<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Action;
use App\Models\Category;
use App\Models\Goal;
use App\Models\MonthlySnapshot;

class FetchDashboardData extends Action
{
    public function __construct(
        private readonly BuildNetWorthSeries $buildNetWorthSeries,
        private readonly BuildAllocationData $buildAllocationData,
        private readonly BuildStackedBar $buildStackedBar,
        private readonly ComputeGrowthRates $computeGrowthRates,
        private readonly ComputeMonthComparison $computeMonthComparison,
        private readonly ComputeForecast $computeForecast,
        private readonly BuildMacroAllocationData $buildMacroAllocationData,
        private readonly BuildMacroStackedBar $buildMacroStackedBar,
        private readonly BuildMacroMonthComparison $buildMacroMonthComparison,
    ) {}

    /** @return array<string, mixed> */
    public function run(): array
    {
        $snapshots = MonthlySnapshot::with('categoryValues.category')
            ->orderBy('date')
            ->get();

        $categories = Category::orderBy('sort_order')->get();

        return [
            'netWorthSeries' => $this->buildNetWorthSeries->run($snapshots),
            'allocationData' => $this->buildAllocationData->run($snapshots, $categories),
            'stackedBar' => $this->buildStackedBar->run($snapshots, $categories),
            'growthRates' => $this->computeGrowthRates->run($snapshots),
            'monthComparison' => $this->computeMonthComparison->run($snapshots, $categories),
            'forecast' => $this->computeForecast->run($snapshots),
            'macroAllocationData' => $this->buildMacroAllocationData->run($snapshots),
            'macroStackedBar' => $this->buildMacroStackedBar->run($snapshots),
            'macroMonthComparison' => $this->buildMacroMonthComparison->run($snapshots),
            'categories' => $categories->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'icon' => $c->icon,
            ])->values()->toArray(),
            'hasData' => $snapshots->count() > 0,
            'latestSnapshot' => $snapshots->last()?->date?->format('Y-m-d'),
            'goal' => ($goal = Goal::first()) ? [
                'name' => $goal->name,
                'target_value' => $goal->target_value,
                'target_date' => $goal->target_date?->format('Y-m-d'),
            ] : null,
        ];
    }
}
