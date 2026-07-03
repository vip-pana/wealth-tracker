<?php

declare(strict_types=1);

namespace App\Actions\Input;

use App\Actions\Action;
use App\Actions\FetchAvailableMonths;
use App\Actions\Snapshots\ComputeValuesAsOf;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use App\Models\Snapshot;
use Illuminate\Support\Carbon;

class FetchInputData extends Action
{
    public function __construct(
        private readonly FetchAssetsByMonth $fetchAssetsByMonth,
        private readonly ResolveSnapshotState $resolveSnapshotState,
        private readonly FetchAvailableMonths $fetchAvailableMonths,
        private readonly ComputeValuesAsOf $computeValuesAsOf,
    ) {}

    /** @return array<string, mixed> */
    public function run(string $month): array
    {
        $prices = AssetPrice::all()->keyBy('ticker');

        $categories = Category::orderBy('sort_order')->get()
            ->reject(fn (Category $c): bool => $c->macro_category?->isIlliquid() ?? false)
            ->values()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'macro_category' => $c->macro_category?->value,
            ]);

        $priceMap = $prices->mapWithKeys(fn (AssetPrice $p) => [$p->ticker => [
            'price' => $p->price,
            'fetched_at' => $p->fetched_at?->toISOString(),
        ]]);

        $lastSnapshot = Snapshot::orderByDesc('date')->first();
        $currentNetWorth = $this->computeValuesAsOf->run(Carbon::now()->toDateString())['total'];

        return [
            'assets' => $this->fetchAssetsByMonth->run($month, $prices),
            'categories' => $categories,
            'month' => $month,
            'availableMonths' => $this->fetchAvailableMonths->run(),
            'snapshotState' => $this->resolveSnapshotState->run($month),
            'lastSnapshotDate' => $lastSnapshot?->date->format('Y-m-d'),
            'currentNetWorth' => $currentNetWorth,
            'prices' => $priceMap,
            'previousValues' => $this->previousValues($month),
        ];
    }

    /**
     * Value each asset held in the most recent month *before* $month that has
     * data, keyed by "category_id|name". The input form compares a freshly
     * entered value against this to warn on a likely typo (a huge jump vs. last
     * month). Empty when $month is the earliest tracked month.
     *
     * @return array<string, float>
     */
    private function previousValues(string $month): array
    {
        $previousMonth = Asset::query()
            ->where('date', '<', $month)
            ->max('date');

        if ($previousMonth === null) {
            return [];
        }

        return Asset::query()
            ->where('date', $previousMonth)
            ->get()
            ->mapWithKeys(fn (Asset $a): array => [$a->category_id.'|'.$a->name => (float) $a->value])
            ->all();
    }
}
