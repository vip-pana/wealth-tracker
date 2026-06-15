<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Actions\Action;
use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportCsv extends Action
{
    public function run(?int $categoryId, ?string $dateFrom, ?string $dateTo): StreamedResponse
    {
        /** @var Collection<int, Category> $categories */
        $categories = Category::orderBy('sort_order')->get();

        /** @var Collection<int, Asset> $assets */
        $assets = Asset::with('category')->orderBy('category_id')->orderBy('date')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wealth-tracker-'.now()->format('Y-m-d').'.csv"',
            'Cache-Control' => 'no-cache, no-store',
        ];

        // Neutralise spreadsheet formula injection: a text cell whose first
        // non-blank character is one of = + - @ (or a tab/CR) is treated as a
        // formula by Excel/Calc. Prefix it with a single quote so it stays a
        // literal string. Only text labels need this; our numeric cells are
        // number_format()ed and never start with a trigger.
        $sanitize = function (string $value): string {
            $trimmed = ltrim($value);
            if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'".$value;
            }

            return $value;
        };

        $callback = function () use ($assets, $categories, $sanitize): void {
            /** @var resource $handle */
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            // Collect sorted unique months
            $months = $assets
                ->map(fn (Asset $a): string => $a->date->format('Y-m'))
                ->unique()
                ->sort()
                ->values()
                ->all();

            /** @var list<string> $months */
            if (count($months) === 0) {
                fclose($handle);

                return;
            }

            // Month display labels: 01-2026, 02-2026, etc.
            $monthLabels = array_map(
                fn (string $m): string => substr($m, 5).'-'.substr($m, 2, 2),
                $months,
            );

            // Build pivot: [category_id][asset_name][YYYY-MM] = value
            /** @var array<int, array<string, array<string, float>>> $pivot */
            $pivot = [];
            // Accumulate net worth and macro totals per month
            /** @var array<string, float> $netWorth */
            $netWorth = [];
            /** @var array<string, array<string, float>> $macroTotals */
            $macroTotals = [];

            foreach ($assets as $asset) {
                $month = $asset->date->format('Y-m');
                $catId = $asset->category_id;
                $pivot[$catId][$asset->name][$month] = $asset->value;

                $netWorth[$month] = ($netWorth[$month] ?? 0.0) + $asset->value;

                $macroKey = $asset->category->macro_category !== null ? $asset->category->macro_category->value : 'Altro';
                $macroTotals[$macroKey][$month] = ($macroTotals[$macroKey][$month] ?? 0.0) + $asset->value;
            }

            // Helper: build a full row with empty cells for missing months
            $valueRow = function (string $label, string $type, array $valuesByMonth) use ($months, $handle, $sanitize): void {
                /** @var array<string, float|null> $valuesByMonth */
                $row = [$sanitize($label), $type, ''];
                foreach ($months as $m) {
                    $v = $valuesByMonth[$m] ?? null;
                    $row[] = $v !== null ? number_format($v, 2, ',', '.') : '';
                }
                fputcsv($handle, $row, ';');
            };

            // Header row
            fputcsv($handle, array_merge(['Net Worth '.Carbon::now()->year, 'Type', ''], $monthLabels), ';');

            // Group categories by macro_category
            /** @var array<string, list<Category>> $byMacro */
            $byMacro = [];
            foreach ($categories as $category) {
                $key = $category->macro_category !== null ? $category->macro_category->value : 'Altro';
                $byMacro[$key][] = $category;
            }

            // Determine section order: ETF, Cripto, Liquidità, then Altro
            $macroOrder = array_map(fn (MacroCategory $mc): string => $mc->value, MacroCategory::cases());
            if (isset($byMacro['Altro'])) {
                $macroOrder[] = 'Altro';
            }

            foreach ($macroOrder as $macroKey) {
                if (! isset($byMacro[$macroKey])) {
                    continue;
                }

                // Section header
                fputcsv($handle, array_merge([$macroKey, '', ''], array_fill(0, count($months), '')), ';');

                foreach ($byMacro[$macroKey] as $category) {
                    $catId = $category->id;
                    if (! isset($pivot[$catId])) {
                        continue;
                    }
                    foreach ($pivot[$catId] as $assetName => $valuesByMonth) {
                        $valueRow('  '.$assetName, '', $valuesByMonth);
                    }
                }

                // Macro subtotal row
                $valueRow('  Subtotale '.$macroKey, 'Total', $macroTotals[$macroKey] ?? []);
            }

            // Blank row
            fputcsv($handle, [], ';');

            // Net worth total row
            $valueRow('Patrimonio Netto (EUR)', 'Total', $netWorth);

            // Blank row
            fputcsv($handle, [], ';');

            // Asset allocation section
            fputcsv($handle, array_merge(['Asset Allocation', '%', ''], array_fill(0, count($months), '')), ';');

            foreach ($macroOrder as $macroKey) {
                if (! isset($macroTotals[$macroKey])) {
                    continue;
                }
                $pctByMonth = [];
                foreach ($months as $m) {
                    $macro = $macroTotals[$macroKey][$m] ?? 0.0;
                    $total = $netWorth[$m] ?? 0.0;
                    $pctByMonth[$m] = $total > 0 ? round($macro / $total * 100, 1) : 0.0;
                }

                // Format as percentage strings
                $row = ['  '.$macroKey, '%', ''];
                foreach ($months as $m) {
                    $row[] = number_format($pctByMonth[$m] ?? 0.0, 1, ',', '.').'%';
                }
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
