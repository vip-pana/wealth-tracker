<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Action;
use App\Models\MonthlySnapshot;
use Illuminate\Support\Collection;

class BuildMacroStackedBar extends Action
{
    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @return array<int, mixed>
     */
    public function run(Collection $snapshots): array
    {
        return $snapshots->map(function (MonthlySnapshot $s): array {
            $entry = ['month' => $s->date->format('Y-m-d')];
            foreach ($s->categoryValues as $cv) {
                $macro = $cv->category->macro_category?->value;
                if ($macro === null) {
                    continue;
                }
                $entry[$macro] = ($entry[$macro] ?? 0.0) + (float) $cv->value;
            }

            return $entry;
        })->values()->toArray();
    }
}
