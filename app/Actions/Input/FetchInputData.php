<?php

declare(strict_types=1);

namespace App\Actions\Input;

use App\Actions\Action;
use App\Actions\Advisor\ComputePositionReturns;
use App\Actions\FetchAvailableMonths;
use App\Actions\Snapshots\BuildNetWorthReconciliation;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use App\Models\Snapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FetchInputData extends Action
{
    public function __construct(
        private readonly FetchAssetsByMonth $fetchAssetsByMonth,
        private readonly ResolveSnapshotState $resolveSnapshotState,
        private readonly FetchAvailableMonths $fetchAvailableMonths,
        private readonly BuildNetWorthReconciliation $buildReconciliation,
        private readonly ComputePositionReturns $computePositionReturns,
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
        $today = Carbon::now()->toDateString();
        $reconciliation = $this->buildReconciliation->run($today, Carbon::now()->format('Y-m'));

        return [
            'assets' => $this->fetchAssetsByMonth->run($month, $prices),
            'categories' => $categories,
            'month' => $month,
            'availableMonths' => $this->fetchAvailableMonths->run(),
            'snapshotState' => $this->resolveSnapshotState->run($month),
            'lastSnapshotDate' => $lastSnapshot?->date->format('Y-m-d'),
            'currentNetWorth' => $reconciliation['total'],
            'reconciliation' => $reconciliation,
            'prices' => $priceMap,
            'previousValues' => $this->previousValues($month, $prices),
            // Whole-history, ISIN-deduplicated: unlike every other prop here it
            // is not scoped to $month, so the positions card labels itself as
            // covering the full history.
            'positionReturns' => $this->computePositionReturns->run(),
        ];
    }

    /**
     * Value each asset held in the most recent month *before* $month that has
     * data, keyed by "category_id|name". Two consumers: the input form warns on a
     * likely typo (a huge jump vs. last month), and the asset table shows the
     * month-over-month change. Empty when $month is the earliest tracked month.
     *
     * Values go through currentValue() rather than reading the `value` column:
     * for a quantity-held asset (ticker + quantity) the column stays 0 and the
     * figure is derived from quantity × price, so the raw column would report a
     * previous value of zero and make every such asset look like a full gain.
     *
     * Both sides are priced at TODAY's price (no historical prices are stored),
     * so for a quantity-held asset the comparison reflects a change in quantity
     * — a contribution — not a price move.
     *
     * @param  Collection<string, AssetPrice>  $prices
     * @return array<string, float>
     */
    private function previousValues(string $month, Collection $prices): array
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
            ->mapWithKeys(fn (Asset $a): array => [
                $a->category_id.'|'.$a->name => $a->currentValue($prices->get($a->ticker ?? '')?->price),
            ])
            ->all();
    }
}
