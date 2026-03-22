<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->get('month', now()->format('Y-m-01'));

        $assets = Asset::with('category')
            ->whereDate('date', $month)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'value' => (float) $a->value,
                'date' => $a->date->format('Y-m-d'),
                'notes' => $a->notes,
                'category_id' => $a->category_id,
                'category' => [
                    'id' => $a->category->id,
                    'name' => $a->category->name,
                    'color' => $a->category->color,
                    'icon' => $a->category->icon,
                ],
            ]);

        $categories = Category::orderBy('sort_order')->get()
            ->map(fn ($c) => [
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

        return Inertia::render('InputData', [
            'assets' => $assets,
            'categories' => $categories,
            'month' => $month,
            'availableMonths' => $availableMonths,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'value' => 'required|numeric|min:0',
            'date' => 'required|date_format:Y-m-d',
            'notes' => 'nullable|string|max:1000',
        ]);

        Asset::create($validated);

        return redirect()->back()->with('success', 'Asset aggiunto.');
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'value' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $asset->update($validated);

        return redirect()->back()->with('success', 'Asset aggiornato.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->delete();

        return redirect()->back()->with('success', 'Asset eliminato.');
    }
}
