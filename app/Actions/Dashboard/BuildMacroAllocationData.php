<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\MonthlySnapshot;
use Illuminate\Support\Collection;

class BuildMacroAllocationData
{
    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @return array<int, mixed>
     */
    public function __invoke(Collection $snapshots): array
    {
        $last = $snapshots->last();
        if (! $last) {
            return [];
        }

        /** @var array<string, float> $totals */
        $totals = [];
        foreach ($last->categoryValues as $cv) {
            $macro = $cv->category->macro_category?->value;
            if ($macro === null) {
                continue;
            }
            $totals[$macro] = ($totals[$macro] ?? 0.0) + (float) $cv->value;
        }

        return array_map(
            fn (string $macro, float $value): array => ['name' => $macro, 'value' => $value],
            array_keys($totals),
            $totals,
        );
    }
}
