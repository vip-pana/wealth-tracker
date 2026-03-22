<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Category;
use App\Models\MonthlySnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function dashboard(): Response
    {
        $snapshots = MonthlySnapshot::with('categoryValues.category')
            ->orderBy('date')
            ->get();

        $categories = Category::orderBy('sort_order')->get();

        $netWorthSeries  = $this->buildNetWorthSeries($snapshots);
        $allocationData  = $this->buildAllocationData($snapshots, $categories);
        $stackedBar      = $this->buildStackedBar($snapshots, $categories);
        $growthRates     = $this->computeGrowthRates($snapshots);
        $monthComparison = $this->computeMonthComparison($snapshots, $categories);
        $forecast        = $this->computeForecast($snapshots);

        return Inertia::render('Dashboard', [
            'netWorthSeries'  => $netWorthSeries,
            'allocationData'  => $allocationData,
            'stackedBar'      => $stackedBar,
            'growthRates'     => $growthRates,
            'monthComparison' => $monthComparison,
            'forecast'        => $forecast,
            'categories'      => $categories->map(fn($c) => [
                'id'    => $c->id,
                'name'  => $c->name,
                'color' => $c->color,
                'icon'  => $c->icon,
            ])->values()->toArray(),
            'hasData'         => $snapshots->count() > 0,
            'latestSnapshot'  => $snapshots->last()?->date?->format('Y-m-d'),
        ]);
    }

    public function analysis(Request $request): Response
    {
        $categoryId = $request->get('category_id');
        $dateFrom   = $request->get('date_from');
        $dateTo     = $request->get('date_to');

        $query = Asset::with('category')->orderByDesc('date')->orderBy('category_id');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }

        $assets = $query->get()->map(fn($a) => [
            'id'       => $a->id,
            'name'     => $a->name,
            'value'    => (float) $a->value,
            'date'     => $a->date->format('Y-m-d'),
            'notes'    => $a->notes,
            'category' => [
                'id'    => $a->category->id,
                'name'  => $a->category->name,
                'color' => $a->category->color,
                'icon'  => $a->category->icon,
            ],
        ]);

        $categories = Category::orderBy('sort_order')->get()->map(fn($c) => [
            'id'    => $c->id,
            'name'  => $c->name,
            'color' => $c->color,
        ]);

        return Inertia::render('Analysis', [
            'assets'     => $assets,
            'categories' => $categories,
            'filters'    => [
                'category_id' => $categoryId ? (int) $categoryId : null,
                'date_from'   => $dateFrom,
                'date_to'     => $dateTo,
            ],
        ]);
    }

    public function exportCsv(Request $request)
    {
        $categoryId = $request->get('category_id');
        $dateFrom   = $request->get('date_from');
        $dateTo     = $request->get('date_to');

        $query = Asset::with('category')->orderBy('date')->orderBy('category_id');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }

        $assets = $query->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wealth-tracker-' . now()->format('Y-m-d') . '.csv"',
            'Cache-Control'       => 'no-cache, no-store',
        ];

        $callback = function () use ($assets) {
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

    private function buildNetWorthSeries(Collection $snapshots): array
    {
        return $snapshots->map(fn($s) => [
            'month'       => $s->date->format('Y-m-d'),
            'total_value' => (float) $s->total_value,
        ])->values()->toArray();
    }

    private function buildAllocationData(Collection $snapshots, Collection $categories): array
    {
        $last = $snapshots->last();
        if (! $last) {
            return [];
        }

        return $last->categoryValues->map(fn($cv) => [
            'name'  => $cv->category->name,
            'value' => (float) $cv->value,
            'color' => $cv->category->color,
        ])->values()->toArray();
    }

    private function buildStackedBar(Collection $snapshots, Collection $categories): array
    {
        return $snapshots->map(function ($s) use ($categories) {
            $entry = ['month' => $s->date->format('Y-m-d')];
            foreach ($categories as $cat) {
                $cv = $s->categoryValues->firstWhere('category_id', $cat->id);
                $entry[$cat->name] = $cv ? (float) $cv->value : 0.0;
            }
            return $entry;
        })->values()->toArray();
    }

    private function computeGrowthRates(Collection $snapshots): array
    {
        $result = [];
        $prev   = null;

        foreach ($snapshots as $s) {
            if ($prev !== null && (float) $prev->total_value > 0) {
                $mom      = (((float) $s->total_value - (float) $prev->total_value) / (float) $prev->total_value) * 100;
                $result[] = [
                    'month'       => $s->date->format('Y-m-d'),
                    'mom_pct'     => round($mom, 2),
                    'total_value' => (float) $s->total_value,
                ];
            }
            $prev = $s;
        }

        return $result;
    }

    private function computeMonthComparison(Collection $snapshots, Collection $categories): array
    {
        if ($snapshots->count() < 2) {
            return [];
        }

        $last   = $snapshots->last();
        $penult = $snapshots->slice(-2, 1)->first();

        return $categories->map(function ($cat) use ($last, $penult) {
            $lastVal   = optional($last->categoryValues->firstWhere('category_id', $cat->id))->value ?? 0;
            $penultVal = optional($penult->categoryValues->firstWhere('category_id', $cat->id))->value ?? 0;

            return [
                'category' => $cat->name,
                'color'    => $cat->color,
                'current'  => (float) $lastVal,
                'previous' => (float) $penultVal,
            ];
        })->values()->toArray();
    }

    private function computeForecast(Collection $snapshots): array
    {
        if ($snapshots->count() < 2) {
            return [];
        }

        $n  = $snapshots->count();
        $xs = range(0, $n - 1);
        $ys = $snapshots->pluck('total_value')->map(fn($v) => (float) $v)->toArray();

        $sumX  = array_sum($xs);
        $sumY  = array_sum($ys);
        $sumXY = array_sum(array_map(fn($x, $y) => $x * $y, $xs, $ys));
        $sumX2 = array_sum(array_map(fn($x) => $x * $x, $xs));

        $denominator = $n * $sumX2 - $sumX ** 2;
        if ($denominator == 0) {
            return [];
        }

        $slope     = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Historical points with trend line
        $historical = $snapshots->values()->map(function ($s, $i) use ($intercept, $slope) {
            return [
                'month'    => $s->date->format('Y-m-d'),
                'actual'   => (float) $s->total_value,
                'trend'    => round($intercept + $slope * $i, 2),
                'forecast' => null,
            ];
        })->toArray();

        // 6-month projection
        $lastDate   = $snapshots->last()->date->copy();
        $projection = [];
        for ($i = 1; $i <= 6; $i++) {
            $x            = $n - 1 + $i;
            $projection[] = [
                'month'    => $lastDate->copy()->addMonths($i)->format('Y-m-d'),
                'actual'   => null,
                'trend'    => null,
                'forecast' => round($intercept + $slope * $x, 2),
            ];
        }

        return array_merge($historical, $projection);
    }
}
