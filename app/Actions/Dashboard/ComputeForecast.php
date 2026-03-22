<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Action;
use App\Models\MonthlySnapshot;
use Illuminate\Support\Collection;

class ComputeForecast extends Action
{
    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @return array<int, mixed>
     */
    public function run(Collection $snapshots): array
    {
        if ($snapshots->count() < 2) {
            return [];
        }

        $n = $snapshots->count();
        $xs = range(0, $n - 1);
        /** @var array<int, float> $ys */
        $ys = $snapshots->map(fn (MonthlySnapshot $s): float => $s->total_value)->toArray();

        $sumX = array_sum($xs);
        $sumY = array_sum($ys);
        $sumXY = 0.0;
        foreach ($xs as $i => $x) {
            $sumXY += $x * $ys[$i];
        }
        $sumX2 = array_sum(array_map(fn (mixed $x): int => (int) $x * (int) $x, $xs));

        $denominator = $n * $sumX2 - $sumX ** 2;
        if ($denominator == 0) {
            return [];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;

        $historical = $snapshots->values()->map(function (MonthlySnapshot $s, int $i) use ($intercept, $slope): array {
            return [
                'month' => $s->date->format('Y-m-d'),
                'actual' => (float) $s->total_value,
                'trend' => round($intercept + $slope * $i, 2),
                'forecast' => null,
            ];
        })->toArray();

        /** @var MonthlySnapshot $lastSnap */
        $lastSnap = $snapshots->last();
        $lastDate = $lastSnap->date->copy();
        $projection = [];
        for ($i = 1; $i <= 6; $i++) {
            $x = $n - 1 + $i;
            $projection[] = [
                'month' => $lastDate->copy()->addMonths($i)->format('Y-m-d'),
                'actual' => null,
                'trend' => null,
                'forecast' => round($intercept + $slope * $x, 2),
            ];
        }

        return array_merge($historical, $projection);
    }
}
