<?php

declare(strict_types=1);

namespace App\Actions\Input;

use App\Actions\Action;
use App\Actions\FetchAvailableMonths;
use App\Actions\Snapshots\BuildNetWorthReconciliation;
use App\Actions\Snapshots\BuildSnapshotDiff;
use App\Enums\MacroCategory;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FetchInputData extends Action
{
    public function __construct(
        private readonly FetchAssetsByMonth $fetchAssetsByMonth,
        private readonly ResolveSnapshotState $resolveSnapshotState,
        private readonly FetchAvailableMonths $fetchAvailableMonths,
        private readonly BuildNetWorthReconciliation $buildReconciliation,
        private readonly BuildSnapshotDiff $buildSnapshotDiff,
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

        $today = Carbon::now()->toDateString();
        $reconciliation = $this->buildReconciliation->run($today, Carbon::now()->format('Y-m'));

        $previousMonth = $this->previousMonth($month);

        return [
            'assets' => $this->fetchAssetsByMonth->run($month, $prices),
            'categories' => $categories,
            'month' => $month,
            'availableMonths' => $this->fetchAvailableMonths->run(),
            'snapshotState' => $this->resolveSnapshotState->run($month),
            'currentNetWorth' => $reconciliation['total'],
            'reconciliation' => $reconciliation,
            // What saving a snapshot now would change, so the confirm dialog can
            // say it rather than just showing a total. Null with no snapshot yet.
            'snapshotDiff' => $this->buildSnapshotDiff->run($today),
            'prices' => $priceMap,
            'previousValues' => $this->previousValues($previousMonth, $prices),
            // Source for the "copy from the previous month" flow: what that
            // month held that this one does not. Null month = nothing to copy.
            'previousMonth' => $previousMonth,
            'copyableAssets' => $this->copyableAssets($month, $previousMonth, $prices),
        ];
    }

    /**
     * The most recent month with asset rows *before* $month — not necessarily
     * the previous calendar month, since a month can be skipped entirely. Null
     * when $month is the earliest tracked month.
     */
    private function previousMonth(string $month): ?string
    {
        /** @var string|null $date */
        $date = Asset::query()
            ->where('date', '<', $month)
            ->max('date');

        return $date;
    }

    /**
     * Value each asset held in $previousMonth, keyed by "category_id|name". Two
     * consumers: the input form warns on a likely typo (a huge jump vs. last
     * month), and the asset table shows the month-over-month change.
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
    private function previousValues(?string $previousMonth, Collection $prices): array
    {
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

    /**
     * Assets held in $previousMonth that have no row in $month yet, so the page
     * can offer to carry them forward. Identity across months is "category and
     * name", the same pairing previousValues() keys on.
     *
     * Values use currentValue() for the same reason as previousValues(): a
     * quantity-held asset would otherwise be listed at the 0 its column holds.
     *
     * @param  Collection<string, AssetPrice>  $prices
     * @return list<array{id: int, name: string, category_id: int, category: string, color: string, value: float}>
     */
    private function copyableAssets(string $month, ?string $previousMonth, Collection $prices): array
    {
        if ($previousMonth === null) {
            return [];
        }

        $alreadyPresent = Asset::query()
            ->where('date', $month)
            ->get()
            ->map(fn (Asset $a): string => $a->category_id.'|'.$a->name)
            ->all();

        $illiquidMacros = MacroCategory::illiquidValues();

        $candidates = Asset::with('category')
            ->where('date', $previousMonth)
            ->when($illiquidMacros !== [], function ($query) use ($illiquidMacros): void {
                $query->whereHas('category', function ($q) use ($illiquidMacros): void {
                    $q->whereNotIn('macro_category', $illiquidMacros)
                        ->orWhereNull('macro_category');
                });
            })
            ->orderBy('created_at')
            ->get();

        $copyable = [];
        foreach ($candidates as $asset) {
            if (in_array($asset->category_id.'|'.$asset->name, $alreadyPresent, true)) {
                continue;
            }

            $copyable[] = [
                'id' => $asset->id,
                'name' => $asset->name,
                'category_id' => $asset->category_id,
                'category' => $asset->category->name,
                'color' => $asset->category->color,
                'value' => $asset->currentValue($prices->get($asset->ticker ?? '')?->price),
            ];
        }

        return $copyable;
    }
}
