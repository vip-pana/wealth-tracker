<?php

declare(strict_types=1);

namespace App\Actions\Input;

use App\Actions\Action;
use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\BankAccount;
use Illuminate\Support\Collection;

class FetchAssetsByMonth extends Action
{
    /**
     * @param  Collection<string, AssetPrice>  $prices
     * @return Collection<int, array{id: int, name: string, ticker: string|null, isin: string|null, wallet_address: string|null, quantity: float|null, price: float|null, value: float, synced_at: string|null, sync_source: string|null, bank_linked: bool, transaction_managed: bool, date: string, notes: string|null, category_id: int, category: array{id: int, name: string, color: string, icon: string|null, macro_category: string|null}}>
     */
    public function run(string $month, Collection $prices): Collection
    {
        $illiquidMacros = MacroCategory::illiquidValues();
        $bankLinks = BankAccount::activeLinkKeys();

        return Asset::with('category')
            ->withCount('transactions')
            ->whereDate('date', $month)
            ->when($illiquidMacros !== [], function ($query) use ($illiquidMacros): void {
                $query->whereHas('category', function ($q) use ($illiquidMacros): void {
                    $q->whereNotIn('macro_category', $illiquidMacros)
                        ->orWhereNull('macro_category');
                });
            })
            ->orderBy('created_at')
            ->get()
            ->map(function (Asset $a) use ($prices, $bankLinks) {
                /** @var AssetPrice|null $priceRecord */
                $priceRecord = $a->ticker !== null ? $prices->get($a->ticker) : null;
                $price = $priceRecord?->price;

                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'ticker' => $a->ticker,
                    'isin' => $a->isin,
                    'wallet_address' => $a->wallet_address,
                    'quantity' => $a->quantity,
                    'price' => $price,
                    'value' => $a->currentValue($price),
                    'synced_at' => $a->synced_at?->toISOString(),
                    'sync_source' => $a->sync_source,
                    'bank_linked' => in_array($a->name.'|'.$a->category_id, $bankLinks, true),
                    'transaction_managed' => (int) $a->transactions_count > 0,
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
