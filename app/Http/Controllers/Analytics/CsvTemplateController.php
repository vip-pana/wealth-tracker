<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvTemplateController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        /** @var Collection<int, Category> $categories */
        $categories = Category::orderBy('sort_order')->get();

        return response()->stream(function () use ($categories): void {
            /** @var resource $handle */
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['data', 'categoria', 'nome_asset', 'valore', 'note'], ';');

            $today = Carbon::now()->startOfMonth()->format('Y-m-d');

            foreach ($categories as $category) {
                fputcsv($handle, [$today, $category->name, 'Esempio Asset', '0.00', ''], ';');
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="import-template-'.now()->format('Y-m-d').'.csv"',
            'Cache-Control' => 'no-cache, no-store',
        ]);
    }
}
