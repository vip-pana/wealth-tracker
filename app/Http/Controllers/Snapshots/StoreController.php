<?php

declare(strict_types=1);

namespace App\Http\Controllers\Snapshots;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSnapshotRequest;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\MonthlySnapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function __invoke(StoreSnapshotRequest $request): RedirectResponse
    {
        /** @var array{month: string} $validated */
        $validated = $request->validated();
        $month = $validated['month'];

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

        return redirect()->back()->with('success', 'Snapshot mensile salvato.');
    }
}
