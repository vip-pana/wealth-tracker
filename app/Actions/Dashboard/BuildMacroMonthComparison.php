<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\MonthlySnapshot;
use Illuminate\Support\Collection;

class BuildMacroMonthComparison
{
    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @return array<int, mixed>
     */
    public function __invoke(Collection $snapshots): array
    {
        if ($snapshots->count() < 2) {
            return [];
        }

        $last = $snapshots->last();
        $penult = $snapshots->slice(-2, 1)->first();

        if (! $last instanceof MonthlySnapshot || ! $penult instanceof MonthlySnapshot) {
            return [];
        }

        /** @var array<string, array{current: float, previous: float}> $totals */
        $totals = [];

        foreach ($last->categoryValues as $cv) {
            $macro = $cv->category->macro_category?->value;
            if ($macro === null) {
                continue;
            }
            $totals[$macro]['current'] = ($totals[$macro]['current'] ?? 0.0) + (float) $cv->value;
            $totals[$macro]['previous'] = $totals[$macro]['previous'] ?? 0.0;
        }

        foreach ($penult->categoryValues as $cv) {
            $macro = $cv->category->macro_category?->value;
            if ($macro === null) {
                continue;
            }
            $totals[$macro]['previous'] = ($totals[$macro]['previous'] ?? 0.0) + (float) $cv->value;
            $totals[$macro]['current'] = $totals[$macro]['current'] ?? 0.0;
        }

        return array_map(
            fn (string $macro, array $values): array => [
                'macro' => $macro,
                'current' => $values['current'],
                'previous' => $values['previous'],
            ],
            array_keys($totals),
            $totals,
        );
    }
}
