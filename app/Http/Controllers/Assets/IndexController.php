<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use App\Models\MonthlySnapshot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $month = $request->string('month', now()->format('Y-m-01'))->value();

        $prices = AssetPrice::all()->keyBy('ticker');

        $assets = Asset::with('category')
            ->whereDate('date', $month)
            ->orderBy('created_at')
            ->get()
            ->map(function (Asset $a) use ($prices) {
                /** @var AssetPrice|null $priceRecord */
                $priceRecord = $a->ticker !== null ? $prices->get($a->ticker) : null;
                $price = $priceRecord?->price;

                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'ticker' => $a->ticker,
                    'wallet_address' => $a->wallet_address,
                    'quantity' => $a->quantity,
                    'price' => $price,
                    'value' => $a->currentValue($price),
                    'date' => $a->date->format('Y-m-d'),
                    'notes' => $a->notes,
                    'category_id' => $a->category_id,
                    'category' => [
                        'id' => $a->category->id,
                        'name' => $a->category->name,
                        'color' => $a->category->color,
                        'icon' => $a->category->icon,
                        'macro_category' => $a->category->macro_category?->value,
                    ],
                ];
            });

        $categories = Category::orderBy('sort_order')->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'icon' => $c->icon,
            ]);

        $availableMonths = Asset::selectRaw("strftime('%Y-%m-01', date) as month")
            ->distinct()
            ->orderByDesc('month')
            ->pluck('month');

        $snapshot = MonthlySnapshot::where('date', $month)->first();
        $latestAssetUpdate = Asset::whereDate('date', $month)->max('updated_at');

        $snapshotState = 'missing';
        if ($snapshot !== null) {
            $snapshotUpdatedAt = $snapshot->updated_at?->toDateTimeString();
            $isStale = $latestAssetUpdate !== null && $snapshotUpdatedAt !== null && $latestAssetUpdate > $snapshotUpdatedAt;
            $snapshotState = $isStale ? 'stale' : 'current';
        }

        $priceMap = $prices->mapWithKeys(fn (AssetPrice $p) => [$p->ticker => [
            'price' => $p->price,
            'fetched_at' => $p->fetched_at->toISOString(),
        ]]);

        return Inertia::render('InputData', [
            'assets' => $assets,
            'categories' => $categories,
            'month' => $month,
            'availableMonths' => $availableMonths,
            'snapshotState' => $snapshotState,
            'prices' => $priceMap,
        ]);
    }
}
