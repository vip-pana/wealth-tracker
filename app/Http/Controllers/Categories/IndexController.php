<?php

declare(strict_types=1);

namespace App\Http\Controllers\Categories;

use App\Http\Controllers\Controller;
use App\Models\AssetPrice;
use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __invoke(): Response
    {
        $categories = Category::orderBy('sort_order')
            ->withCount('assets')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'icon' => $c->icon,
                'sort_order' => $c->sort_order,
                'macro_category' => $c->macro_category?->value,
                'assets_count' => $c->assets_count,
            ]);

        $prices = AssetPrice::all()->map(fn (AssetPrice $p) => [
            'ticker' => $p->ticker,
            'price' => $p->price,
            'currency' => $p->currency,
            'fetched_at' => $p->fetched_at->toISOString(),
        ]);

        return Inertia::render('Settings', [
            'categories' => $categories,
            'prices' => $prices,
        ]);
    }
}
