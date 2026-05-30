<?php

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Actions\Action;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Snapshot;

class FetchGoalData extends Action
{
    /** @return array<string, mixed> */
    public function run(): array
    {
        $goal = Goal::with(['categoryAllocations', 'milestones'])->first();

        $categories = Category::orderBy('sort_order')->get();

        $latestSnapshot = Snapshot::with('categoryValues')
            ->orderByDesc('date')
            ->first();

        $currentNetWorth = $latestSnapshot?->total_value;

        $currentAllocation = $latestSnapshot
            ? $latestSnapshot->categoryValues->map(fn ($v) => [
                'category_id' => $v->category_id,
                'value' => $v->value,
            ])->values()->toArray()
            : [];

        $currentMacroAllocation = [];
        if ($latestSnapshot) {
            $macroTotals = [];
            foreach ($latestSnapshot->categoryValues as $v) {
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
            $categoryAllocations = $goal->categoryAllocations
                ->whereNull('macro_category')
                ->map(fn ($a) => [
                    'category_id' => $a->category_id,
                    'macro_category' => null,
                    'percentage' => $a->percentage,
                ])->values()->toArray();

            $macroAllocations = $goal->categoryAllocations
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
                    'notes' => $m->notes,
                    'target_value' => $m->target_value,
                    'target_date' => $m->target_date->format('Y-m-d'),
                ])->values()->toArray();

            $goalData = [
                'id' => $goal->id,
                'name' => $goal->name,
                'description' => $goal->description,
                'target_value' => $goal->target_value,
                'target_date' => $goal->target_date?->format('Y-m-d'),
                'categoryAllocations' => $categoryAllocations,
                'macroAllocations' => $macroAllocations,
                'milestones' => $milestones,
            ];
        }

        return [
            'goal' => $goalData,
            'categories' => $categories->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'macro_category' => $c->macro_category?->value,
            ])->values()->toArray(),
            'currentNetWorth' => $currentNetWorth,
            'currentAllocation' => $currentAllocation,
            'currentMacroAllocation' => $currentMacroAllocation,
        ];
    }
}
