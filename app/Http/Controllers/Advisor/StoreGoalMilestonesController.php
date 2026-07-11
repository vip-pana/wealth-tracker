<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advisor\StoreGoalMilestonesRequest;
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

        // Replace ONLY the milestones, leaving the goal core and target
        // composition untouched.
        $goal->milestones()->delete();

        /** @var array<int, array<string, string|float|null>> $milestones */
        $milestones = $request->input('milestones', []);
        foreach ($milestones as $milestone) {
            $notes = isset($milestone['notes']) ? (string) $milestone['notes'] : '';
            $goal->milestones()->create([
                'notes' => $notes !== '' ? $notes : null,
                'target_value' => (float) $milestone['target_value'],
                'target_date' => (string) $milestone['target_date'],
            ]);
        }

        return redirect()->back()->with('success', 'Tappe intermedie salvate.');
    }
}
