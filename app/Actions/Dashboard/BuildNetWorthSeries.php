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
}
