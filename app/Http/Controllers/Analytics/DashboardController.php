<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Actions\Dashboard\BuildAllocationData;
use App\Actions\Dashboard\BuildMacroAllocationData;
use App\Actions\Dashboard\BuildMacroMonthComparison;
use App\Actions\Dashboard\BuildMacroStackedBar;
use App\Actions\Dashboard\BuildNetWorthSeries;
use App\Actions\Dashboard\BuildStackedBar;
use App\Actions\Dashboard\ComputeForecast;
use App\Actions\Dashboard\ComputeGrowthRates;
use App\Actions\Dashboard\ComputeMonthComparison;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MonthlySnapshot;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
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

    public function __invoke(): Response
    {
        $snapshots = MonthlySnapshot::with('categoryValues.category')
            ->orderBy('date')
            ->get();

        $categories = Category::orderBy('sort_order')->get();

        return Inertia::render('Dashboard', [
            'netWorthSeries' => ($this->buildNetWorthSeries)($snapshots),
            'allocationData' => ($this->buildAllocationData)($snapshots, $categories),
            'stackedBar' => ($this->buildStackedBar)($snapshots, $categories),
            'growthRates' => ($this->computeGrowthRates)($snapshots),
            'monthComparison' => ($this->computeMonthComparison)($snapshots, $categories),
            'forecast' => ($this->computeForecast)($snapshots),
            'macroAllocationData' => ($this->buildMacroAllocationData)($snapshots),
            'macroStackedBar' => ($this->buildMacroStackedBar)($snapshots),
            'macroMonthComparison' => ($this->buildMacroMonthComparison)($snapshots),
            'categories' => $categories->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'icon' => $c->icon,
            ])->values()->toArray(),
            'hasData' => $snapshots->count() > 0,
            'latestSnapshot' => $snapshots->last()?->date?->format('Y-m-d'),
        ]);
    }
}
