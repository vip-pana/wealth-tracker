<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Advisor\AdvisorOutputFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advisor\StoreGoalCompositionRequest;
use App\Models\Category;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;

class StoreGoalCompositionController extends Controller
{
    public function __construct(private readonly AdvisorOutputFilter $filter) {}

    public function __invoke(StoreGoalCompositionRequest $request): RedirectResponse
    {
        $goal = Goal::query()->first();

        if (! $goal instanceof Goal) {
            return redirect()->back()->with('error', 'Definisci prima l\'obiettivo, poi la composizione target.');
        }

        /** @var array<int, array<string, string|float>> $allocations */
        $allocations = $request->input('allocations', []);

        // The same numeric channel the milestones carry, without the glide
        // path: a target weight on a category the user named after an
        // instrument. Refused before the delete below, so a rejected
        // submission costs the user nothing. See AdvisorOutputFilter.
        $names = [];
        foreach ($allocations as $alloc) {
            if (is_string($alloc['category'] ?? null)) {
                $names[] = $alloc['category'];
            }
        }

        $named = $this->filter->instrumentNames($names);
        if ($named !== []) {
            return redirect()->back()->with('error', AdvisorOutputFilter::allocationRefusal(...$named));
        }

        // The advisor suggests per-category weights and the user edited the exact
        // percentages on the card. Replace the whole target composition with what
        // the user submitted, mapping each category name to its id so the goal's
        // target allocation stays per-category (the same shape the Goal section
        // uses). Milestones and goal core are left untouched.
        $categoryIds = Category::query()->pluck('id', 'name');

        $goal->categoryAllocations()->delete();

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
