<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\MonthlySnapshot;
use Illuminate\Support\Collection;

class ComputeGrowthRates
{
    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @return array<int, mixed>
     */
    public function __invoke(Collection $snapshots): array
    {
        $result = [];
        $prev = null;

        foreach ($snapshots as $s) {
            if ($prev !== null && (float) $prev->total_value > 0) {
                $mom = (((float) $s->total_value - (float) $prev->total_value) / (float) $prev->total_value) * 100;
                $result[] = [
                    'month' => $s->date->format('Y-m-d'),
                    'mom_pct' => round($mom, 2),
                    'total_value' => (float) $s->total_value,
                ];
            }
            $prev = $s;
        }

        return $result;
    }
}
