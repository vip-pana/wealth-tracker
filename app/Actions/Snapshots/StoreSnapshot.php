<?php

declare(strict_types=1);

namespace App\Actions\Snapshots;

use App\Actions\Action;
use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use App\Models\MonthlySnapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Support\Facades\DB;

class StoreSnapshot extends Action
{
    public function run(string $month): void
    {
        DB::transaction(function () use ($month) {
            $prices = AssetPrice::all()->keyBy('ticker');

            $assets = Asset::whereDate('date', $month)->get();

            $byCat = [];
            $total = 0.0;
            foreach ($assets as $asset) {
                /** @var AssetPrice|null $priceRecord */
                $priceRecord = $asset->ticker !== null ? $prices->get($asset->ticker) : null;
                $value = $asset->currentValue($priceRecord?->price);
                $total += $value;
                $catId = $asset->category_id;
                $byCat[$catId] = ($byCat[$catId] ?? 0.0) + $value;
            }

            foreach ($this->carryForwardAnnualValues($month, array_keys($byCat)) as $catId => $value) {
                $byCat[$catId] = $value;
                $total += $value;
            }

            $snapshot = MonthlySnapshot::updateOrCreate(
                ['date' => $month],
                ['total_value' => $total]
            );

            foreach ($byCat as $catId => $value) {
                SnapshotCategoryValue::updateOrCreate(
                    ['snapshot_id' => $snapshot->id, 'category_id' => $catId],
                    ['value' => $value]
                );
            }

            $activeCatIds = array_keys($byCat);
            SnapshotCategoryValue::where('snapshot_id', $snapshot->id)
                ->whereNotIn('category_id', $activeCatIds)
                ->delete();
        });
    }

    /**
     * @param  array<int, int>  $alreadyCountedCategoryIds
     * @return array<int, float>
     */
    private function carryForwardAnnualValues(string $month, array $alreadyCountedCategoryIds): array
    {
        $annualMacros = array_values(array_filter(
            MacroCategory::cases(),
            fn (MacroCategory $m): bool => $m->isAnnual(),
        ));

        if ($annualMacros === []) {
            return [];
        }

        $annualCategories = Category::query()
            ->whereIn('macro_category', array_map(fn (MacroCategory $m): string => $m->value, $annualMacros))
            ->whereNotIn('id', $alreadyCountedCategoryIds)
            ->get();

        $result = [];
        foreach ($annualCategories as $category) {
            $lastAsset = Asset::query()
                ->where('category_id', $category->id)
                ->whereDate('date', '<=', $month)
                ->orderByDesc('date')
                ->first();

            if ($lastAsset === null) {
                continue;
            }

            $sum = (float) Asset::query()
                ->where('category_id', $category->id)
                ->whereDate('date', $lastAsset->date->format('Y-m-d'))
                ->sum('value');

            $result[$category->id] = $sum;
        }

        return $result;
    }
}
