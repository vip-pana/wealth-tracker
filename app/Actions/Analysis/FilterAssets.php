<?php

declare(strict_types=1);

namespace App\Actions\Analysis;

use App\Actions\Action;
use App\Enums\MacroCategory;
use App\Models\Asset;
use Illuminate\Support\Collection;

class FilterAssets extends Action
{
    /**
     * @return Collection<int, array{id: int, name: string, value: float, date: string, notes: string|null, category: array{id: int, name: string, color: string, icon: string|null}}>
     */
    public function run(?int $categoryId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $illiquidMacros = MacroCategory::illiquidValues();
        $query = Asset::with('category')
            ->whereHas('category', function ($q) use ($illiquidMacros): void {
                if ($illiquidMacros === []) {
                    return;
                }
                $q->where(function ($inner) use ($illiquidMacros): void {
                    $inner->whereNotIn('macro_category', $illiquidMacros)
                        ->orWhereNull('macro_category');
                });
            })
            ->orderByDesc('date')
            ->orderBy('category_id');

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }
        if ($dateFrom !== null) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->whereDate('date', '<=', $dateTo);
        }

        return $query->get()->map(fn (Asset $a) => [
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
    }
}
