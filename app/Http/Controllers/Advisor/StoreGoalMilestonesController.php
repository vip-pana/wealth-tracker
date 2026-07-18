<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advisor\StoreGoalMilestonesRequest;
use App\Models\Category;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;

class StoreGoalMilestonesController extends Controller
{
    public function __invoke(StoreGoalMilestonesRequest $request): RedirectResponse
    {
        $goal = Goal::query()->first();

        // Milestones hang off a goal: without one there is nothing to attach them
        // to. The advisor is told to propose the goal core first, so this is a
        // safety net, not a normal path.
        if (! $goal instanceof Goal) {
            return redirect()->back()->with('error', 'Definisci prima l\'obiettivo, poi le tappe intermedie.');
        }

        // Replace ONLY the milestones, leaving the goal core untouched. Each
        // milestone carries its own target allocation (the glide-path). The
        // milestones are soft-deleted, so their per-milestone allocation rows
        // won't cascade automatically — drop them explicitly first to avoid
        // orphans, then recreate everything from the submitted set.
        $milestoneIds = $goal->milestones()->pluck('id');
        $goal->categoryAllocations()->whereIn('milestone_id', $milestoneIds)->delete();
        $goal->milestones()->delete();

        $categoryIds = Category::query()->pluck('id', 'name');

        /** @var array<int, array<string, mixed>> $milestones */
        $milestones = $request->input('milestones', []);
        foreach ($milestones as $milestone) {
            $notes = is_string($milestone['notes'] ?? null) ? $milestone['notes'] : '';
            $action = is_string($milestone['action'] ?? null) ? $milestone['action'] : '';
            $rationale = is_string($milestone['rationale'] ?? null) ? $milestone['rationale'] : '';
            $created = $goal->milestones()->create([
                'notes' => $notes !== '' ? $notes : null,
                'action' => $action !== '' ? $action : null,
                'rationale' => $rationale !== '' ? $rationale : null,
                'target_value' => is_numeric($milestone['target_value'] ?? null) ? (float) $milestone['target_value'] : 0.0,
                'target_date' => is_string($milestone['target_date'] ?? null) ? $milestone['target_date'] : '',
            ]);

            $allocation = is_array($milestone['allocation'] ?? null) ? $milestone['allocation'] : [];
            foreach ($allocation as $alloc) {
                if (! is_array($alloc)) {
                    continue;
                }
                $name = is_string($alloc['category'] ?? null) ? $alloc['category'] : '';
                $goal->categoryAllocations()->create([
                    'milestone_id' => $created->id,
                    'category_id' => $categoryIds[$name] ?? null,
                    'macro_category' => null,
                    'percentage' => is_numeric($alloc['percentage'] ?? null) ? (float) $alloc['percentage'] : 0.0,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Tappe intermedie salvate.');
    }
}
