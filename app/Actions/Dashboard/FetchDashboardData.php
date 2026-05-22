<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Action;
use App\Enums\MacroCategory;
use App\Models\Category;
use App\Models\Goal;
use App\Models\MonthlySnapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Support\Collection;

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
        $allSnapshots = MonthlySnapshot::with('categoryValues.category')
            ->orderBy('date')
            ->get();

        $allCategories = Category::orderBy('sort_order')->get();

        /** @var array<int, int> $illiquidCategoryIds */
        $illiquidCategoryIds = $allCategories
            ->filter(fn (Category $c): bool => $c->macro_category?->isIlliquid() ?? false)
            ->map(fn (Category $c): int => $c->id)
            ->values()
            ->all();

        $latestSnapshot = $allSnapshots->last();
        $totalNetWorth = $latestSnapshot instanceof MonthlySnapshot ? (float) $latestSnapshot->total_value : 0.0;
        $illiquidTotal = $this->illiquidTotalFor($latestSnapshot, $illiquidCategoryIds);
        $liquidTotal = $totalNetWorth - $illiquidTotal;

        $liquidSnapshots = $this->stripIlliquid($allSnapshots, $illiquidCategoryIds);
        $liquidCategories = $allCategories->reject(fn (Category $c): bool => in_array($c->id, $illiquidCategoryIds, true))->values();

        return [
            'netWorthSeries' => $this->buildNetWorthSeries->run($liquidSnapshots),
            'allocationData' => $this->buildAllocationData->run($liquidSnapshots, $liquidCategories),
            'stackedBar' => $this->buildStackedBar->run($liquidSnapshots, $liquidCategories),
            'growthRates' => $this->computeGrowthRates->run($liquidSnapshots),
            'monthComparison' => $this->computeMonthComparison->run($liquidSnapshots, $liquidCategories),
            'forecast' => $this->computeForecast->run($liquidSnapshots),
            'macroAllocationData' => $this->buildMacroAllocationData->run($liquidSnapshots),
            'macroStackedBar' => $this->buildMacroStackedBar->run($liquidSnapshots),
            'macroMonthComparison' => $this->buildMacroMonthComparison->run($liquidSnapshots),
            'categories' => $liquidCategories->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'icon' => $c->icon,
            ])->values()->toArray(),
            'hasData' => $liquidSnapshots->count() > 0,
            'latestSnapshot' => $latestSnapshot?->date?->format('Y-m-d'),
            'totalNetWorth' => $totalNetWorth,
            'liquidNetWorth' => $liquidTotal,
            'illiquidNetWorth' => $illiquidTotal,
            'hasIlliquid' => $illiquidTotal > 0,
            'illiquidMacros' => MacroCategory::illiquidValues(),
            'goal' => ($goal = Goal::first()) ? [
                'name' => $goal->name,
                'target_value' => $goal->target_value,
                'target_date' => $goal->target_date?->format('Y-m-d'),
            ] : null,
        ];
    }

    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @param  array<int, int>  $illiquidCategoryIds
     * @return Collection<int, MonthlySnapshot>
     */
    private function stripIlliquid(Collection $snapshots, array $illiquidCategoryIds): Collection
    {
        if ($illiquidCategoryIds === []) {
            return $snapshots;
        }

        return $snapshots->map(function (MonthlySnapshot $snapshot) use ($illiquidCategoryIds): MonthlySnapshot {
            $liquidValues = $snapshot->categoryValues->reject(
                fn (SnapshotCategoryValue $cv): bool => in_array($cv->category_id, $illiquidCategoryIds, true)
            )->values();

            $liquidTotal = (float) $liquidValues->sum(fn (SnapshotCategoryValue $cv): float => (float) $cv->value);
            $snapshot->setRelation('categoryValues', $liquidValues);
            $snapshot->total_value = $liquidTotal;

            return $snapshot;
        });
    }

    /**
     * @param  array<int, int>  $illiquidCategoryIds
     */
    private function illiquidTotalFor(?MonthlySnapshot $snapshot, array $illiquidCategoryIds): float
    {
        if ($snapshot === null || $illiquidCategoryIds === []) {
            return 0.0;
        }

        return (float) $snapshot->categoryValues
            ->filter(fn (SnapshotCategoryValue $cv): bool => in_array($cv->category_id, $illiquidCategoryIds, true))
            ->sum(fn (SnapshotCategoryValue $cv): float => (float) $cv->value);
    }
}
