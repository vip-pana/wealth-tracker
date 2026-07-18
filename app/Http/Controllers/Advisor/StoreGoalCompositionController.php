<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advisor\StoreGoalCompositionRequest;
use App\Models\Category;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;

class StoreGoalCompositionController extends Controller
{
    public function __invoke(StoreGoalCompositionRequest $request): RedirectResponse
    {
        $goal = Goal::query()->first();

        if (! $goal instanceof Goal) {
            return redirect()->back()->with('error', 'Definisci prima l\'obiettivo, poi la composizione target.');
        }

        // The advisor suggests per-category weights and the user edited the exact
        // percentages on the card. Replace the whole target composition with what
        // the user submitted, mapping each category name to its id so the goal's
        // target allocation stays per-category (the same shape the Goal section
        // uses). Milestones and goal core are left untouched.
        $categoryIds = Category::query()->pluck('id', 'name');

        $goal->categoryAllocations()->delete();

        /** @var array<int, array<string, string|float>> $allocations */
        $allocations = $request->input('allocations', []);
        foreach ($allocations as $alloc) {
            $name = (string) $alloc['category'];
            $goal->categoryAllocations()->create([
                'category_id' => $categoryIds[$name] ?? null,
                'macro_category' => null,
                'percentage' => (float) $alloc['percentage'],
            ]);
        }

        return redirect()->back()->with('success', 'Composizione target salvata.');
    }
}
