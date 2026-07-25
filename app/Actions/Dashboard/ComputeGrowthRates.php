<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Action;
use App\Models\Snapshot;
use Illuminate\Support\Collection;

class ComputeGrowthRates extends Action
{
    /**
     * @param  Collection<int, Snapshot>  $snapshots
     * @return array<int, mixed>
     */
    public function run(Collection $snapshots): array
    {
        $result = [];
        $prev = null;

        foreach ($snapshots as $s) {
            if ($prev !== null && (float) $prev->total_value > 0) {
                $mom = (((float) $s->total_value - (float) $prev->total_value) / (float) $prev->total_value) * 100;
                $result[] = [
                    'date' => $s->date->format('Y-m-d'),
                    'change_pct' => round($mom, 2),
                    'total_value' => (float) $s->total_value,
                ];
            }
            $prev = $s;
        }

        return $result;
    }
}
