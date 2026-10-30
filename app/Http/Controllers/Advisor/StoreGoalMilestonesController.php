<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Advisor\AdvisorOutputFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advisor\StoreGoalMilestonesRequest;
use App\Models\Category;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;

class StoreGoalMilestonesController extends Controller
{
    public function __construct(private readonly AdvisorOutputFilter $filter) {}

    public function __invoke(StoreGoalMilestonesRequest $request): RedirectResponse
    {
        $goal = Goal::query()->first();

        // Milestones hang off a goal: without one there is nothing to attach them
        // to. The advisor is told to propose the goal core first, so this is a
        // safety net, not a normal path.
        if (! $goal instanceof Goal) {
            return redirect()->back()->with('error', 'Definisci prima l\'obiettivo, poi le tappe intermedie.');
        }

        /** @var array<int, array<string, mixed>> $submitted */
        $submitted = $request->input('milestones', []);

        // The numeric channel. `notes` / `action` / `rationale` go through the
        // filter below, but the allocation is percentages against category
        // names and no lexical check can reach it — and the request validates
        // only its shape. A weight on a category the user named after an
        // instrument is the proposition the filter removes from the text,
        // persisted as a row and redrawn on the Goal page indefinitely.
        //
        // Refused before the delete, and for the whole submission: this write
        // replaces every milestone, so skipping one after the fact would cost
        // the user a plan they already had in exchange for one they cannot see
        // the hole in.
        $named = $this->instrumentBuckets($submitted);
        if ($named !== []) {
            return redirect()->back()->with('error', AdvisorOutputFilter::allocationRefusal(...$named));
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

        foreach ($submitted as $milestone) {
            // The three free-text fields are the model's own words, posted back
            // from the proposal card. Once saved they are shown on the Goal page
            // for good, outside the advisor and outside its footer, so they go
            // through the firewall here as well as on the way out.
            $notes = $this->screen($milestone['notes'] ?? null);
            $action = $this->screen($milestone['action'] ?? null);
            $rationale = $this->screen($milestone['rationale'] ?? null);
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
                    'cap_amount' => is_numeric($alloc['cap_amount'] ?? null) ? (float) $alloc['cap_amount'] : null,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Tappe intermedie salvate.');
    }

    private function screen(mixed $value): string
    {
        return is_string($value) ? $this->filter->filterField($value) : '';
    }

    /**
     * Every category name across the submitted milestones' allocations that
     * names a specific instrument rather than an asset class.
     *
     * @param  array<int, array<string, mixed>>  $milestones
     * @return list<string>
     */
    private function instrumentBuckets(array $milestones): array
    {
        $names = [];

        foreach ($milestones as $milestone) {
            $allocation = is_array($milestone['allocation'] ?? null) ? $milestone['allocation'] : [];

            foreach ($allocation as $alloc) {
                if (is_array($alloc) && is_string($alloc['category'] ?? null)) {
                    $names[] = $alloc['category'];
                }
            }
        }

        return $this->filter->instrumentNames($names);
    }
}
