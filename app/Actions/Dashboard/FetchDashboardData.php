<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Action;
use App\Actions\Advisor\ComputePortfolioMetrics;
use App\Actions\Advisor\ComputePositionReturns;
use App\Enums\MacroCategory;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Snapshot;
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
        private readonly ComputePortfolioMetrics $computePortfolioMetrics,
        private readonly ComputePositionReturns $computePositionReturns,
    ) {}

    /** @return array<string, mixed> */
    public function run(): array
    {
        $allSnapshots = Snapshot::with('categoryValues.category')
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
        $totalNetWorth = $latestSnapshot instanceof Snapshot ? (float) $latestSnapshot->total_value : 0.0;
        $illiquidTotal = $this->illiquidTotalFor($latestSnapshot, $illiquidCategoryIds);
        $liquidTotal = $totalNetWorth - $illiquidTotal;

        $liquidSnapshots = $this->stripIlliquid($allSnapshots, $illiquidCategoryIds);
        $liquidCategories = $allCategories->reject(fn (Category $c): bool => in_array($c->id, $illiquidCategoryIds, true))->values();

        $monthlySnapshots = $this->collapseToMonthly($liquidSnapshots);

        $goal = Goal::with('milestones')->first();

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
            'momNetWorthSeries' => $this->buildNetWorthSeries->run($monthlySnapshots),
            'momStackedBar' => $this->buildStackedBar->run($monthlySnapshots, $liquidCategories),
            'momGrowthRates' => $this->computeGrowthRates->run($monthlySnapshots),
            'momMonthComparison' => $this->computeMonthComparison->run($monthlySnapshots, $liquidCategories),
            'momForecast' => $this->computeForecast->run($monthlySnapshots),
            'momMacroStackedBar' => $this->buildMacroStackedBar->run($monthlySnapshots),
            'momMacroMonthComparison' => $this->buildMacroMonthComparison->run($monthlySnapshots),
            'categories' => $liquidCategories->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
            ])->values()->toArray(),
            'hasData' => $liquidSnapshots->count() > 0,
            'latestSnapshot' => $latestSnapshot?->date?->format('Y-m-d'),
            'totalNetWorth' => $totalNetWorth,
            'liquidNetWorth' => $liquidTotal,
            'illiquidNetWorth' => $illiquidTotal,
            'hasIlliquid' => $illiquidTotal > 0,
            'illiquidMacros' => MacroCategory::illiquidValues(),
            'goal' => $goal ? [
                'name' => $goal->name,
                'target_value' => $goal->target_value,
                'target_date' => $goal->target_date?->format('Y-m-d'),
                'milestones' => $goal->milestones
                    ->map(fn ($m): array => ['target_value' => $m->target_value])
                    ->values()
                    ->toArray(),
            ] : null,
            'portfolioMetrics' => $this->computePortfolioMetrics->run($monthlySnapshots, $liquidCategories, $goal),
            'positionReturns' => $this->computePositionReturns->run(),
        ];
    }

    /**
     * Collapse a date-ordered collection to one snapshot per calendar month,
     * keeping the last snapshot of each month as that month's value.
     *
     * @param  Collection<int, Snapshot>  $snapshots
     * @return Collection<int, Snapshot>
     */
    private function collapseToMonthly(Collection $snapshots): Collection
    {
        return $snapshots
            ->keyBy(fn (Snapshot $s): string => $s->date->format('Y-m'))
            ->sortKeys()
            ->values();
    }

    /**
     * @param  Collection<int, Snapshot>  $snapshots
     * @param  array<int, int>  $illiquidCategoryIds
     * @return Collection<int, Snapshot>
     */
    private function stripIlliquid(Collection $snapshots, array $illiquidCategoryIds): Collection
    {
        if ($illiquidCategoryIds === []) {
            return $snapshots;
        }

        return $snapshots->map(function (Snapshot $snapshot) use ($illiquidCategoryIds): Snapshot {
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
    private function illiquidTotalFor(?Snapshot $snapshot, array $illiquidCategoryIds): float
    {
        if (! $snapshot instanceof Snapshot || $illiquidCategoryIds === []) {
            return 0.0;
        }

        return (float) $snapshot->categoryValues
            ->filter(fn (SnapshotCategoryValue $cv): bool => in_array($cv->category_id, $illiquidCategoryIds, true))
            ->sum(fn (SnapshotCategoryValue $cv): float => (float) $cv->value);
    }
}
