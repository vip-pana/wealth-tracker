<?php

declare(strict_types=1);

namespace App\Actions\Input;

use App\Actions\Action;
use App\Models\Asset;
use App\Models\MonthlySnapshot;

class ResolveSnapshotState extends Action
{
    public function run(string $month): string
    {
        $snapshot = MonthlySnapshot::where('date', $month)->first();

        if ($snapshot === null) {
            return 'missing';
        }

        $latestAssetUpdate = Asset::whereDate('date', $month)->max('updated_at');
        $snapshotUpdatedAt = $snapshot->updated_at?->toDateTimeString();
        $isStale = $latestAssetUpdate !== null && $snapshotUpdatedAt !== null && $latestAssetUpdate > $snapshotUpdatedAt;

        return $isStale ? 'stale' : 'current';
    }
}
