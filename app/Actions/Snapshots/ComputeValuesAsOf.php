<?php

declare(strict_types=1);

namespace App\Actions\Snapshots;

use App\Actions\Action;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use Illuminate\Support\Collection;

class ComputeValuesAsOf extends Action
{
    /**
     * Net worth as of $date: for each category, the assets of its most recent
     * date on or before $date, priced live for tickers.
     *
     * @return array{byCategory: array<int, float>, total: float}
     */
    public function run(string $date): array
    {
        $prices = AssetPrice::all()->keyBy('ticker');

        $byCategory = [];
        $total = 0.0;
        foreach (Category::all() as $category) {
            $value = $this->latestKnownValue($category->id, $date, $prices);

            if ($value === null) {
                continue;
            }

            $byCategory[$category->id] = $value;
            $total += $value;
        }

        return ['byCategory' => $byCategory, 'total' => $total];
    }

    /**
     * @param  Collection<string, AssetPrice>  $prices
     */
    private function latestKnownValue(int $categoryId, string $date, Collection $prices): ?float
    {
        $latestDate = Asset::query()
            ->where('category_id', $categoryId)
            ->whereDate('date', '<=', $date)
            ->max('date');

        if (! is_string($latestDate)) {
            return null;
        }

        $assets = Asset::query()
            ->where('category_id', $categoryId)
            ->whereDate('date', $latestDate)
            ->get();

        $value = 0.0;
        foreach ($assets as $asset) {
            /** @var AssetPrice|null $priceRecord */
            $priceRecord = $asset->ticker !== null ? $prices->get($asset->ticker) : null;
            $value += $asset->currentValue($priceRecord?->price);
        }

        return $value;
    }
}
