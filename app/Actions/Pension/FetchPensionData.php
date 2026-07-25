<?php

declare(strict_types=1);

namespace App\Actions\Pension;

use App\Actions\Action;
use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Support\Carbon;

class FetchPensionData extends Action
{
    /** @return array<string, mixed> */
    public function run(): array
    {
        $illiquidMacros = MacroCategory::illiquidValues();

        $categories = Category::query()
            ->whereIn('macro_category', $illiquidMacros)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'macro_category' => $c->macro_category?->value,
            ])
            ->values();

        $entries = Asset::with('category')
            ->whereHas('category', function ($q) use ($illiquidMacros): void {
                $q->whereIn('macro_category', $illiquidMacros);
            })
            ->orderByDesc('date')
            ->get()
            ->map(fn (Asset $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'value' => (float) $a->value,
                'year' => $a->date->year,
                'date' => $a->date->format('Y-m-d'),
                'notes' => $a->notes,
                'category_id' => $a->category_id,
                'category' => [
                    'id' => $a->category->id,
                    'name' => $a->category->name,
                    'color' => $a->category->color,
                ],
            ])
            ->values();

        $currentYear = Carbon::now()->year;
        /** @var list<int> $availableYears */
        $availableYears = range($currentYear - 20, $currentYear);

        $latestByCategory = [];
        foreach ($entries as $entry) {
            $catId = $entry['category_id'];
            if (! isset($latestByCategory[$catId]) || $entry['year'] > $latestByCategory[$catId]['year']) {
                $latestByCategory[$catId] = $entry;
            }
        }

        $totalCurrent = (float) array_sum(array_column($latestByCategory, 'value'));

        return [
            'categories' => $categories->toArray(),
            'entries' => $entries->toArray(),
            'availableYears' => array_reverse($availableYears),
            'totalCurrent' => $totalCurrent,
        ];
    }
}
