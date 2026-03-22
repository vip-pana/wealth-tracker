<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\MonthlySnapshot;
use Illuminate\Support\Collection;

class BuildNetWorthSeries
{
    /** @param Collection<int, MonthlySnapshot> $snapshots
     * @return array<int, mixed>
     */
    public function __invoke(Collection $snapshots): array
    {
        return $snapshots->map(fn (MonthlySnapshot $s): array => [
            'month' => $s->date->format('Y-m-d'),
            'total_value' => (float) $s->total_value,
        ])->values()->toArray();
    }
}
