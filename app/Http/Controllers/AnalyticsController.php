<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Category;
use App\Models\MonthlySnapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function dashboard(): Response
    {
        $snapshots = MonthlySnapshot::with('categoryValues.category')
            ->orderBy('date')
            ->get();

        $categories = Category::orderBy('sort_order')->get();

        $netWorthSeries = $this->buildNetWorthSeries($snapshots);
        $allocationData = $this->buildAllocationData($snapshots, $categories);
        $stackedBar = $this->buildStackedBar($snapshots, $categories);
        $growthRates = $this->computeGrowthRates($snapshots);
        $monthComparison = $this->computeMonthComparison($snapshots, $categories);
        $forecast = $this->computeForecast($snapshots);
        $macroAllocationData = $this->buildMacroAllocationData($snapshots);
        $macroStackedBar = $this->buildMacroStackedBar($snapshots);
        $macroMonthComparison = $this->buildMacroMonthComparison($snapshots);

        return Inertia::render('Dashboard', [
            'netWorthSeries' => $netWorthSeries,
            'allocationData' => $allocationData,
            'stackedBar' => $stackedBar,
            'growthRates' => $growthRates,
            'monthComparison' => $monthComparison,
            'forecast' => $forecast,
            'macroAllocationData' => $macroAllocationData,
            'macroStackedBar' => $macroStackedBar,
            'macroMonthComparison' => $macroMonthComparison,
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

    public function analysis(Request $request): Response
    {
        $categoryId = $request->input('category_id');
        $dateFrom = $request->has('date_from') ? $request->string('date_from')->value() : null;
        $dateTo = $request->has('date_to') ? $request->string('date_to')->value() : null;

        $query = Asset::with('category')->orderByDesc('date')->orderBy('category_id');

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }
        if ($dateFrom !== null) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->whereDate('date', '<=', $dateTo);
        }

        $assets = $query->get()->map(fn (Asset $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'value' => (float) $a->value,
            'date' => $a->date->format('Y-m-d'),
            'notes' => $a->notes,
            'category' => [
                'id' => $a->category->id,
                'name' => $a->category->name,
                'color' => $a->category->color,
                'icon' => $a->category->icon,
            ],
        ]);

        $categories = Category::orderBy('sort_order')->get()->map(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'color' => $c->color,
        ]);

        /** @var list<string> $availableMonths */
        $availableMonths = Asset::selectRaw("strftime('%Y-%m-01', date) as month")
            ->groupByRaw("strftime('%Y-%m', date)")
            ->orderByDesc('month')
            ->pluck('month')
            ->all();

        return Inertia::render('Analysis', [
            'assets' => $assets,
            'categories' => $categories,
            'availableMonths' => $availableMonths,
            'filters' => [
                'category_id' => $categoryId !== null ? $request->integer('category_id') : null,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $categoryId = $request->input('category_id');
        $dateFrom = $request->has('date_from') ? $request->string('date_from')->value() : null;
        $dateTo = $request->has('date_to') ? $request->string('date_to')->value() : null;

        $query = Asset::with('category')->orderBy('date')->orderBy('category_id');

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }
        if ($dateFrom !== null) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->whereDate('date', '<=', $dateTo);
        }

        $assets = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wealth-tracker-'.now()->format('Y-m-d').'.csv"',
            'Cache-Control' => 'no-cache, no-store',
        ];

        $callback = function () use ($assets): void {
            /** @var resource $handle */
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Data', 'Categoria', 'Nome Asset', 'Valore (€)', 'Note'], ';');
            foreach ($assets as $asset) {
                fputcsv($handle, [
                    $asset->date->format('Y-m-d'),
                    $asset->category->name,
                    $asset->name,
                    number_format((float) $asset->value, 2, ',', '.'),
                    $asset->notes ?? '',
                ], ';');
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    /** @param Collection<int, MonthlySnapshot> $snapshots
     * @return array<int, mixed>
     */
    private function buildNetWorthSeries(Collection $snapshots): array
    {
        return $snapshots->map(fn (MonthlySnapshot $s): array => [
            'month' => $s->date->format('Y-m-d'),
            'total_value' => (float) $s->total_value,
        ])->values()->toArray();
    }

    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @param  Collection<int, Category>  $categories
     * @return array<int, mixed>
     */
    private function buildAllocationData(Collection $snapshots, Collection $categories): array
    {
        $last = $snapshots->last();
        if (! $last) {
            return [];
        }

        return $last->categoryValues->map(fn (SnapshotCategoryValue $cv): array => [
            'name' => $cv->category->name,
            'value' => (float) $cv->value,
            'color' => $cv->category->color,
        ])->values()->toArray();
    }

    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @param  Collection<int, Category>  $categories
     * @return array<int, mixed>
     */
    private function buildStackedBar(Collection $snapshots, Collection $categories): array
    {
        return $snapshots->map(function (MonthlySnapshot $s) use ($categories): array {
            $entry = ['month' => $s->date->format('Y-m-d')];
            foreach ($categories as $cat) {
                /** @var SnapshotCategoryValue|null $cv */
                $cv = $s->categoryValues->firstWhere('category_id', $cat->id);
                $entry[$cat->name] = $cv !== null ? (float) $cv->value : 0.0;
            }

            return $entry;
        })->values()->toArray();
    }

    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @return array<int, mixed>
     */
    private function computeGrowthRates(Collection $snapshots): array
    {
        $result = [];
        $prev = null;

        foreach ($snapshots as $s) {
            if ($prev !== null && (float) $prev->total_value > 0) {
                $mom = (((float) $s->total_value - (float) $prev->total_value) / (float) $prev->total_value) * 100;
                $result[] = [
                    'month' => $s->date->format('Y-m-d'),
                    'mom_pct' => round($mom, 2),
                    'total_value' => (float) $s->total_value,
                ];
            }
            $prev = $s;
        }

        return $result;
    }

    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @param  Collection<int, Category>  $categories
     * @return array<int, mixed>
     */
    private function computeMonthComparison(Collection $snapshots, Collection $categories): array
    {
        if ($snapshots->count() < 2) {
            return [];
        }

        $last = $snapshots->last();
        $penult = $snapshots->slice(-2, 1)->first();

        if (! $last instanceof MonthlySnapshot || ! $penult instanceof MonthlySnapshot) {
            return [];
        }

        return $categories->map(function (Category $cat) use ($last, $penult): array {
            /** @var SnapshotCategoryValue|null $lastCv */
            $lastCv = $last->categoryValues->firstWhere('category_id', $cat->id);
            /** @var SnapshotCategoryValue|null $penultCv */
            $penultCv = $penult->categoryValues->firstWhere('category_id', $cat->id);

            return [
                'category' => $cat->name,
                'color' => $cat->color,
                'current' => $lastCv !== null ? (float) $lastCv->value : 0.0,
                'previous' => $penultCv !== null ? (float) $penultCv->value : 0.0,
            ];
        })->values()->toArray();
    }

    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @return array<int, mixed>
     */
    private function computeForecast(Collection $snapshots): array
    {
        if ($snapshots->count() < 2) {
            return [];
        }

        $n = $snapshots->count();
        $xs = range(0, $n - 1);
        /** @var array<int, float> $ys */
        $ys = $snapshots->map(fn (MonthlySnapshot $s): float => $s->total_value)->toArray();

        $sumX = array_sum($xs);
        $sumY = array_sum($ys);
        $sumXY = 0.0;
        foreach ($xs as $i => $x) {
            $sumXY += $x * $ys[$i];
        }
        $sumX2 = array_sum(array_map(fn (mixed $x): int => (int) $x * (int) $x, $xs));

        $denominator = $n * $sumX2 - $sumX ** 2;
        if ($denominator == 0) {
            return [];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Historical points with trend line
        $historical = $snapshots->values()->map(function (MonthlySnapshot $s, int $i) use ($intercept, $slope): array {
            return [
                'month' => $s->date->format('Y-m-d'),
                'actual' => (float) $s->total_value,
                'trend' => round($intercept + $slope * $i, 2),
                'forecast' => null,
            ];
        })->toArray();

        // 6-month projection
        /** @var MonthlySnapshot $lastSnap */
        $lastSnap = $snapshots->last();
        $lastDate = $lastSnap->date->copy();
        $projection = [];
        for ($i = 1; $i <= 6; $i++) {
            $x = $n - 1 + $i;
            $projection[] = [
                'month' => $lastDate->copy()->addMonths($i)->format('Y-m-d'),
                'actual' => null,
                'trend' => null,
                'forecast' => round($intercept + $slope * $x, 2),
            ];
        }

        return array_merge($historical, $projection);
    }

    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @return array<int, mixed>
     */
    private function buildMacroAllocationData(Collection $snapshots): array
    {
        $last = $snapshots->last();
        if (! $last) {
            return [];
        }

        /** @var array<string, float> $totals */
        $totals = [];
        foreach ($last->categoryValues as $cv) {
            $macro = $cv->category->macro_category?->value;
            if ($macro === null) {
                continue;
            }
            $totals[$macro] = ($totals[$macro] ?? 0.0) + (float) $cv->value;
        }

        return array_map(
            fn (string $macro, float $value): array => ['name' => $macro, 'value' => $value],
            array_keys($totals),
            $totals,
        );
    }

    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @return array<int, mixed>
     */
    private function buildMacroStackedBar(Collection $snapshots): array
    {
        return $snapshots->map(function (MonthlySnapshot $s): array {
            $entry = ['month' => $s->date->format('Y-m-d')];
            foreach ($s->categoryValues as $cv) {
                $macro = $cv->category->macro_category?->value;
                if ($macro === null) {
                    continue;
                }
                $entry[$macro] = ($entry[$macro] ?? 0.0) + (float) $cv->value;
            }

            return $entry;
        })->values()->toArray();
    }

    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @return array<int, mixed>
     */
    private function buildMacroMonthComparison(Collection $snapshots): array
    {
        if ($snapshots->count() < 2) {
            return [];
        }

        $last = $snapshots->last();
        $penult = $snapshots->slice(-2, 1)->first();

        if (! $last instanceof MonthlySnapshot || ! $penult instanceof MonthlySnapshot) {
            return [];
        }

        /** @var array<string, array{current: float, previous: float}> $totals */
        $totals = [];

        foreach ($last->categoryValues as $cv) {
            $macro = $cv->category->macro_category?->value;
            if ($macro === null) {
                continue;
            }
            $totals[$macro]['current'] = ($totals[$macro]['current'] ?? 0.0) + (float) $cv->value;
            $totals[$macro]['previous'] = $totals[$macro]['previous'] ?? 0.0;
        }

        foreach ($penult->categoryValues as $cv) {
            $macro = $cv->category->macro_category?->value;
            if ($macro === null) {
                continue;
            }
            $totals[$macro]['previous'] = ($totals[$macro]['previous'] ?? 0.0) + (float) $cv->value;
            $totals[$macro]['current'] = $totals[$macro]['current'] ?? 0.0;
        }

        return array_map(
            fn (string $macro, array $values): array => [
                'macro' => $macro,
                'current' => $values['current'],
                'previous' => $values['previous'],
            ],
            array_keys($totals),
            $totals,
        );
    }
}
