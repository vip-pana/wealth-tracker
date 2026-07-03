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
     * date on or before $date, priced live for tickers. `asOf` records the month
     * each category's value was actually taken from — a category with no row in
     * the reference month is carried forward from an earlier one, which is why
     * the snapshot total can exceed the sum of the current month's rows.
     *
     * @return array{byCategory: array<int, float>, total: float, asOf: array<int, string>}
     */
    public function run(string $date): array
    {
        $prices = AssetPrice::all()->keyBy('ticker');

        $byCategory = [];
        $asOf = [];
        $total = 0.0;
        foreach (Category::all() as $category) {
            $resolved = $this->latestKnownValue($category->id, $date, $prices);

            if ($resolved === null) {
                continue;
            }

            $byCategory[$category->id] = $resolved['value'];
            $asOf[$category->id] = $resolved['date'];
            $total += $resolved['value'];
        }

        return ['byCategory' => $byCategory, 'total' => $total, 'asOf' => $asOf];
    }

    /**
     * @param  Collection<string, AssetPrice>  $prices
     * @return array{value: float, date: string}|null
     */
    private function latestKnownValue(int $categoryId, string $date, Collection $prices): ?array
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

        return ['value' => $value, 'date' => $latestDate];
    }
}
