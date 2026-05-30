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

        /** @var array<int, array<string, string|int|float>> $categoryAllocations */
        $categoryAllocations = $request->input('category_allocations', []);
        foreach ($categoryAllocations as $alloc) {
            $goal->categoryAllocations()->create([
                'category_id' => (int) $alloc['category_id'],
                'macro_category' => null,
                'percentage' => (float) $alloc['percentage'],
            ]);
        }

        /** @var array<int, array<string, string|float>> $macroAllocations */
        $macroAllocations = $request->input('macro_allocations', []);
        foreach ($macroAllocations as $alloc) {
            $goal->categoryAllocations()->create([
                'category_id' => null,
                'macro_category' => (string) $alloc['macro_category'],
                'percentage' => (float) $alloc['percentage'],
            ]);
        }

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

        return redirect()->route('goal.index')->with('success', 'Obiettivo creato.');
    }
}
