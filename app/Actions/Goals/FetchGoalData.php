<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Actions\Action;
use App\Advisor\AdvisorOutputFilter;
use App\Models\Category;
use App\Models\Goal;
use App\Models\InvestorProfile;
use App\Models\Snapshot;
use Illuminate\Support\Carbon;

class FetchGoalData extends Action
{
    public function __construct(private readonly AdvisorOutputFilter $filter) {}

    /** @return array<string, mixed> */
    public function run(): array
    {
        $goal = Goal::with(['categoryAllocations', 'milestones.categoryAllocations'])->first();

        $categories = Category::orderBy('sort_order')->get();

        $latestSnapshot = Snapshot::with('categoryValues')
            ->orderByDesc('date')
            ->first();

        // The goal measures the INVESTABLE portfolio: emergency-fund / parked-cash
        // categories (investable = false) are money the user deliberately keeps
        // out of the plan, so they're excluded from the current net worth and the
        // current-vs-target allocation the goal compares against.
        $nonInvestableIds = $categories
            ->filter(fn (Category $c): bool => $c->investable === false)
            ->pluck('id')
            ->all();

        $investableValues = $latestSnapshot
            ? $latestSnapshot->categoryValues->reject(fn ($v): bool => in_array($v->category_id, $nonInvestableIds, true))
            : collect();

        $currentNetWorth = $latestSnapshot
            ? (float) $investableValues->sum(fn ($v): float => (float) $v->value)
            : null;

        $currentAllocation = $investableValues->map(fn ($v) => [
            'category_id' => $v->category_id,
            'value' => $v->value,
        ])->values()->toArray();

        $currentMacroAllocation = [];
        if ($latestSnapshot) {
            $macroTotals = [];
            foreach ($investableValues as $v) {
                $category = $categories->firstWhere('id', $v->category_id);
                $macro = $category?->macro_category !== null ? $category->macro_category->value : 'Altro';
                $macroTotals[$macro] = ($macroTotals[$macro] ?? 0.0) + $v->value;
            }
            foreach ($macroTotals as $macro => $value) {
                $currentMacroAllocation[] = ['macro_category' => $macro, 'value' => $value];
            }
        }

        $goalData = null;
        if ($goal !== null) {
            // The target shown as "vs current" is the CURRENT glide-path step —
            // the next unreached milestone's allocation — not a single global
            // target. Falls back to the goal's global allocation for a goal
            // without per-milestone allocations.
            $targetAllocation = $goal->currentTargetAllocation((float) ($currentNetWorth ?? 0));

            $categoryAllocations = $targetAllocation
                ->whereNull('macro_category')
                ->map(fn ($a) => [
                    'category_id' => $a->category_id,
                    'macro_category' => null,
                    'percentage' => $a->percentage,
                ])->values()->toArray();

            $macroAllocations = $targetAllocation
                ->whereNotNull('macro_category')
                ->map(fn ($a) => [
                    'category_id' => null,
                    'macro_category' => $a->macro_category,
                    'percentage' => $a->percentage,
                ])->values()->toArray();

            $milestones = $goal->milestones
                ->sortBy('target_date')
                ->map(fn ($m) => [
                    'id' => $m->id,
                    // Written by the advisor and kept on this page for good, so
                    // they are screened on the way out as well — rows saved
                    // before the firewall covered this path are in the database.
                    'notes' => $this->screen($m->notes),
                    'action' => $this->screen($m->action),
                    'rationale' => $this->screen($m->rationale),
                    'target_value' => $m->target_value,
                    'target_date' => $m->target_date->format('Y-m-d'),
                    'allocation' => $m->categoryAllocations
                        ->map(fn ($a) => [
                            'category_id' => $a->category_id,
                            'percentage' => $a->percentage,
                            'cap_amount' => $a->cap_amount,
                        ])->values()->toArray(),
                ])->values()->toArray();

            $goalData = [
                'id' => $goal->id,
                // Both are the advisor's words when it proposed the goal, and
                // the name is derived from the description when none was
                // proposed, so the two carry the same text.
                'name' => $goal->displayName(),
                'description' => $goal->displayDescription(),
                'target_value' => $goal->target_value,
                'target_date' => $goal->target_date?->format('Y-m-d'),
                'categoryAllocations' => $categoryAllocations,
                'macroAllocations' => $macroAllocations,
                'milestones' => $milestones,
            ];
        }

        // The investor profile lives beside the goal: both describe "who I am and
        // where I'm heading", so the Goal page owns the profile card and the
        // advisor only links to it. The horizon is derived from the goal's target
        // date rather than read from the profile row.
        $profile = InvestorProfile::query()->first();

        return [
            'goal' => $goalData,
            'profile' => $profile !== null ? [
                'name' => $profile->name,
                'birth_date' => $profile->birth_date?->format('Y-m-d'),
                'horizon' => $goal?->horizon(),
                'risk_tolerance' => $profile->risk_tolerance,
                // Written by the advisor — `memory` without even a confirmation
                // click — and shown on this page outside the chat, so they are
                // screened here the same way the milestones are.
                'notes' => $profile->displayNotes(),
                'memory' => $profile->displayMemory(),
            ] : null,
            'categories' => $categories->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'macro_category' => $c->macro_category?->value,
            ])->values()->toArray(),
            'currentNetWorth' => $currentNetWorth,
            'currentAllocation' => $currentAllocation,
            'currentMacroAllocation' => $currentMacroAllocation,
            'today' => Carbon::today()->format('Y-m-d'),
        ];
    }

    private function screen(?string $value): ?string
    {
        return $value === null ? null : $this->filter->filterField($value);
    }
}
