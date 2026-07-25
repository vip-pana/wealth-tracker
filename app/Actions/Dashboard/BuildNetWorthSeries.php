<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Action;
use App\Models\Snapshot;
use Illuminate\Support\Collection;

class BuildNetWorthSeries extends Action
{
    /** @param Collection<int, Snapshot> $snapshots
     * @return array<int, mixed>
     */
    public function run(Collection $snapshots): array
    {
        return $snapshots->map(fn (Snapshot $s): array => [
            'date' => $s->date->format('Y-m-d'),
            'total_value' => (float) $s->total_value,
        ])->values()->toArray();
    }

    /**
     * The net-worth series with three layers per point, so the chart can show
     * total wealth, wealth excluding the (illiquid) pension, and the investable
     * portfolio (excluding both pension and the emergency-fund buffer). Takes the
     * FULL snapshots (not the investment-stripped ones) so `total_value` is the
     * real net worth and no layer dips just because money moved into an excluded
     * category. For dates before the buffer categories existed, `investable`
     * naturally equals `ex_pension` (nothing was tagged yet).
     *
     * @param  Collection<int, Snapshot>  $snapshots  full snapshots (nothing stripped)
     * @param  array<int, int>  $pensionCategoryIds
     * @param  array<int, int>  $bufferCategoryIds
     * @return array<int, array{date: string, total_value: float, ex_pension: float, investable: float}>
     */
    public function runLayered(Collection $snapshots, array $pensionCategoryIds, array $bufferCategoryIds): array
    {
        $series = [];
        foreach ($snapshots->values() as $s) {
            $total = 0.0;
            $pension = 0.0;
            $buffer = 0.0;
            foreach ($s->categoryValues as $cv) {
                $v = (float) $cv->value;
                $total += $v;
                if (in_array($cv->category_id, $pensionCategoryIds, true)) {
                    $pension += $v;
                }
                if (in_array($cv->category_id, $bufferCategoryIds, true)) {
                    $buffer += $v;
                }
            }

            $series[] = [
                'date' => $s->date->format('Y-m-d'),
                'total_value' => round($total, 2),
                'ex_pension' => round($total - $pension, 2),
                'investable' => round($total - $pension - $buffer, 2),
            ];
        }

        return $series;
    }
}
