<?php

declare(strict_types=1);

namespace App\Actions\Input;

use App\Actions\Action;
use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\AssetPrice;
use Illuminate\Support\Collection;

class FetchAssetsByMonth extends Action
{
    /**
     * @param  Collection<string, AssetPrice>  $prices
     * @return Collection<int, array{id: int, name: string, ticker: string|null, wallet_address: string|null, quantity: float|null, price: float|null, value: float, bank_synced_at: string|null, date: string, notes: string|null, category_id: int, category: array{id: int, name: string, color: string, icon: string|null, macro_category: string|null}}>
     */
    public function run(string $month, Collection $prices): Collection
    {
        $illiquidMacros = MacroCategory::illiquidValues();

        return Asset::with('category')
            ->whereDate('date', $month)
            ->when($illiquidMacros !== [], function ($query) use ($illiquidMacros): void {
                $query->whereHas('category', function ($q) use ($illiquidMacros): void {
                    $q->whereNotIn('macro_category', $illiquidMacros)
                        ->orWhereNull('macro_category');
                });
            })
            ->orderBy('created_at')
            ->get()
            ->map(function (Asset $a) use ($prices) {
                /** @var AssetPrice|null $priceRecord */
                $priceRecord = $a->ticker !== null ? $prices->get($a->ticker) : null;
                $price = $priceRecord?->price;

                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'ticker' => $a->ticker,
                    'wallet_address' => $a->wallet_address,
                    'quantity' => $a->quantity,
                    'price' => $price,
                    'value' => $a->currentValue($price),
                    'bank_synced_at' => $a->bank_synced_at?->toISOString(),
                    'date' => $a->date->format('Y-m-d'),
                    'notes' => $a->notes,
                    'category_id' => $a->category_id,
                    'category' => [
                        'id' => $a->category->id,
                        'name' => $a->category->name,
                        'color' => $a->category->color,
                        'icon' => $a->category->icon,
                        'macro_category' => $a->category->macro_category?->value,
                    ],
                ];
            });
    }
}
