<?php

declare(strict_types=1);

namespace App\Actions\Input;

use App\Actions\Action;
use App\Actions\FetchAvailableMonths;
use App\Models\AssetPrice;
use App\Models\Category;
use Illuminate\Http\Request;

class FetchInputData extends Action
{
    public function __construct(
        private readonly FetchAssetsByMonth $fetchAssetsByMonth,
        private readonly ResolveSnapshotState $resolveSnapshotState,
        private readonly FetchAvailableMonths $fetchAvailableMonths,
    ) {}

    /** @return array<string, mixed> */
    public function run(Request $request): array
    {
        $month = $request->string('month', now()->format('Y-m-01'))->value();

        $prices = AssetPrice::all()->keyBy('ticker');

        $categories = Category::orderBy('sort_order')->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'icon' => $c->icon,
            ]);

        $priceMap = $prices->mapWithKeys(fn (AssetPrice $p) => [$p->ticker => [
            'price' => $p->price,
            'fetched_at' => $p->fetched_at->toISOString(),
        ]]);

        return [
            'assets' => $this->fetchAssetsByMonth->run($month, $prices),
            'categories' => $categories,
            'month' => $month,
            'availableMonths' => $this->fetchAvailableMonths->run(),
            'snapshotState' => $this->resolveSnapshotState->run($month),
            'prices' => $priceMap,
        ];
    }
}
