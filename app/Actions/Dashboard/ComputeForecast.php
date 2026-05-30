<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Action;
use App\Models\Snapshot;
use Illuminate\Support\Collection;

class ComputeForecast extends Action
{
    /**
     * @param  Collection<int, Snapshot>  $snapshots
     * @return array<int, mixed>
     */
    public function run(Collection $snapshots): array
    {
        if ($snapshots->count() < 2) {
            return [];
        }

        $ordered = $snapshots->values();

        /** @var Snapshot $firstSnap */
        $firstSnap = $ordered->first();
        $origin = $firstSnap->date->copy()->startOfDay();

        /** @var array<int, float> $xs */
        $xs = $ordered->map(fn (Snapshot $s): float => (float) $origin->diffInDays($s->date))->toArray();
        /** @var array<int, float> $ys */
        $ys = $ordered->map(fn (Snapshot $s): float => $s->total_value)->toArray();

        $n = count($xs);
        $sumX = array_sum($xs);
        $sumY = array_sum($ys);
        $sumXY = 0.0;
        $sumX2 = 0.0;
        foreach ($xs as $i => $x) {
            $sumXY += $x * $ys[$i];
            $sumX2 += $x * $x;
        }

        $denominator = $n * $sumX2 - $sumX ** 2;
        if ($denominator == 0.0) {
            return [];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;

        $historical = $ordered->map(function (Snapshot $s, int $i) use ($intercept, $slope, $xs): array {
            return [
                'date' => $s->date->format('Y-m-d'),
                'actual' => (float) $s->total_value,
                'trend' => round($intercept + $slope * $xs[$i], 2),
                'forecast' => null,
            ];
        })->toArray();

        /** @var Snapshot $lastSnap */
        $lastSnap = $ordered->last();
        $lastDate = $lastSnap->date->copy();
        $projection = [];
        for ($i = 1; $i <= 6; $i++) {
            $projectedDate = $lastDate->copy()->addMonths($i);
            $x = (float) $origin->diffInDays($projectedDate);
            $projection[] = [
                'date' => $projectedDate->format('Y-m-d'),
                'actual' => null,
                'trend' => null,
                'forecast' => round($intercept + $slope * $x, 2),
            ];
        }

        return array_merge($historical, $projection);
    }
}
