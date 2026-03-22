<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalysisController extends Controller
{
    public function __invoke(Request $request): Response
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
}
