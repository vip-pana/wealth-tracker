<?php

declare(strict_types=1);

namespace App\Http\Controllers\Goals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Goals\StoreGoalRequest;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    public function __invoke(StoreGoalRequest $request): RedirectResponse
    {
        $goal = Goal::create([
            'name' => $request->string('name')->value(),
            'description' => $request->string('description')->value() ?: null,
            'target_value' => $request->float('target_value'),
            'target_date' => $request->string('target_date')->value() ?: null,
        ]);

        /** @var array<int, array<string, mixed>> $milestones */
        $milestones = $request->input('milestones', []);
        $this->saveMilestones($goal, $milestones);

        return redirect()->route('goal.index')->with('success', 'Obiettivo creato.');
    }

    /**
     * Persist a goal's milestones and each milestone's own target allocation
     * (the glide-path). The target allocation now lives per-milestone; there is
     * no global goal allocation to write anymore.
     *
     * @param  array<int, array<string, mixed>>  $milestones
     */
    private function saveMilestones(Goal $goal, array $milestones): void
    {
        foreach ($milestones as $milestone) {
            $notes = is_string($milestone['notes'] ?? null) ? $milestone['notes'] : '';
            $created = $goal->milestones()->create([
                'notes' => $notes !== '' ? $notes : null,
                'target_value' => is_numeric($milestone['target_value'] ?? null) ? (float) $milestone['target_value'] : 0.0,
                'target_date' => is_string($milestone['target_date'] ?? null) ? $milestone['target_date'] : '',
            ]);

            $allocation = is_array($milestone['allocation'] ?? null) ? $milestone['allocation'] : [];
            foreach ($allocation as $alloc) {
                if (! is_array($alloc)) {
                    continue;
                }
                $goal->categoryAllocations()->create([
                    'milestone_id' => $created->id,
                    'category_id' => is_numeric($alloc['category_id'] ?? null) ? (int) $alloc['category_id'] : null,
                    'macro_category' => null,
                    'percentage' => is_numeric($alloc['percentage'] ?? null) ? (float) $alloc['percentage'] : 0.0,
                ]);
            }
        }
    }
}
