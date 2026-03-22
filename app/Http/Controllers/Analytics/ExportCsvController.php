<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportCsvController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
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
}
