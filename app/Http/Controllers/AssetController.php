<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BulkMoveAssetsRequest;
use App\Http\Requests\CopyAssetsRequest;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use App\Models\MonthlySnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
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

        // Get available months from existing assets for the month picker
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

    public function store(StoreAssetRequest $request): RedirectResponse
    {
        Asset::create($request->validated());

        return redirect()->back()->with('success', 'Asset aggiunto.');
    }

    public function update(UpdateAssetRequest $request, Asset $asset): RedirectResponse
    {
        $asset->update($request->validated());

        return redirect()->back()->with('success', 'Asset aggiornato.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->delete();

        return redirect()->back()->with('success', 'Asset eliminato.');
    }

    public function copyFromMonth(CopyAssetsRequest $request): RedirectResponse
    {
        $sourceDate = $request->string('source_date')->value();
        $targetDate = $request->string('month', now()->format('Y-m-01'))->value();

        Asset::whereDate('date', $sourceDate)
            ->get()
            ->each(function (Asset $asset) use ($targetDate): void {
                Asset::create([
                    'category_id' => $asset->category_id,
                    'name' => $asset->name,
                    'ticker' => $asset->ticker,
                    'wallet_address' => $asset->wallet_address,
                    'quantity' => $asset->quantity,
                    'value' => $asset->value,
                    'date' => $targetDate,
                    'notes' => $asset->notes,
                ]);
            });

        return redirect()->back()->with('success', 'Asset copiati.');
    }

    public function bulkMove(BulkMoveAssetsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Asset::whereIn('id', $validated['asset_ids'])
            ->update(['date' => $validated['target_date']]);

        return redirect()->back()->with('success', 'Asset spostati.');
    }
}
