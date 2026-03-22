<?php

declare(strict_types=1);

namespace App\Actions\Snapshots;

use App\Actions\Action;
use App\Models\Asset;
use App\Models\AssetPrice;
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
}
