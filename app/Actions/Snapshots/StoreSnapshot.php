<?php

declare(strict_types=1);

namespace App\Actions\Snapshots;

use App\Actions\Action;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use App\Models\Snapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoreSnapshot extends Action
{
    public function run(string $date): void
    {
        DB::transaction(function () use ($date) {
            $prices = AssetPrice::all()->keyBy('ticker');

            $byCat = [];
            $total = 0.0;
            foreach (Category::all() as $category) {
                $value = $this->latestKnownValue($category->id, $date, $prices);

                if ($value === null) {
                    continue;
                }

                $byCat[$category->id] = $value;
                $total += $value;
            }

            $snapshot = Snapshot::updateOrCreate(
                ['date' => $date],
                ['total_value' => $total]
            );

            foreach ($byCat as $catId => $value) {
                SnapshotCategoryValue::updateOrCreate(
                    ['snapshot_id' => $snapshot->id, 'category_id' => $catId],
                    ['value' => $value]
                );
            }

            SnapshotCategoryValue::where('snapshot_id', $snapshot->id)
                ->whereNotIn('category_id', array_keys($byCat))
                ->delete();
        });
    }

    /**
     * Value of a category as of $date: the assets of its most recent date on or
     * before $date, priced live for tickers.
     *
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
