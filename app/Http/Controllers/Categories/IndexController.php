<?php

declare(strict_types=1);

namespace App\Http\Controllers\Categories;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Category;
use App\Models\Goal;
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

        $prices = AssetPrice::orderBy('ticker')->get()->map(fn (AssetPrice $p) => [
            'ticker' => $p->ticker,
            'price' => $p->price,
            'currency' => $p->currency,
            'fetched_at' => $p->fetched_at?->toISOString(),
            'last_status' => $p->last_status,
            'last_attempt_at' => $p->last_attempt_at?->toISOString(),
            'last_error' => $p->last_error,
        ]);

        return Inertia::render('Settings', [
            'categories' => $categories,
            'prices' => $prices,
            'trashed' => $this->trashedItems(),
        ]);
    }

    /** @return list<array{type: string, label: string, deleted_at: string|null, restore_url: string}> */
    private function trashedItems(): array
    {
        $items = [];

        foreach (Asset::onlyTrashed()->latest('deleted_at')->get() as $a) {
            $items[] = [
                'type' => 'Asset',
                'label' => $a->name,
                'deleted_at' => $a->deleted_at?->toISOString(),
                'restore_url' => route('assets.restore', $a->id, absolute: false),
            ];
        }

        foreach (Category::onlyTrashed()->latest('deleted_at')->get() as $c) {
            $items[] = [
                'type' => 'Categoria',
                'label' => $c->name,
                'deleted_at' => $c->deleted_at?->toISOString(),
                'restore_url' => route('categories.restore', $c->id, absolute: false),
            ];
        }

        foreach (Goal::onlyTrashed()->latest('deleted_at')->get() as $g) {
            $items[] = [
                'type' => 'Obiettivo',
                'label' => $g->name,
                'deleted_at' => $g->deleted_at?->toISOString(),
                'restore_url' => route('goal.restore', $g->id, absolute: false),
            ];
        }

        return $items;
    }
}
