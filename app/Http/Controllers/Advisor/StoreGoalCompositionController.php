<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advisor\StoreGoalCompositionRequest;
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

        // The advisor only suggests macro buckets, and the user edited the exact
        // percentages on the card. Replace the whole target composition with what
        // the user submitted — this wipes any per-category allocation set
        // manually, which the card copy warns about. Milestones and goal core are
        // left untouched.
        $goal->categoryAllocations()->delete();

        /** @var array<int, array<string, string|float>> $macroAllocations */
        $macroAllocations = $request->input('macro_allocations', []);
        foreach ($macroAllocations as $alloc) {
            $goal->categoryAllocations()->create([
                'category_id' => null,
                'macro_category' => (string) $alloc['macro_category'],
                'percentage' => (float) $alloc['percentage'],
            ]);
        }

        return redirect()->back()->with('success', 'Composizione target salvata.');
    }
}
