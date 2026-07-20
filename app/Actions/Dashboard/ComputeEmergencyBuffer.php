<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Action;
use App\Models\Category;
use App\Models\Snapshot;
use App\Models\SnapshotCategoryValue;

class ComputeEmergencyBuffer extends Action
{
    /**
     * The emergency-fund buffer: the value of the non-investable categories
     * (emergency fund / parked cash) in the latest snapshot. Mirrors the
     * carve-out FetchDashboardData exposes as `bufferNetWorth`, extracted so
     * the Cashflow page can reuse it without duplicating the logic.
     */
    public function run(): float
    {
        /** @var array<int, int> $nonInvestableIds */
        $nonInvestableIds = Category::query()
            ->where('investable', false)
            ->pluck('id')
            ->all();

        if ($nonInvestableIds === []) {
            return 0.0;
        }

        $latest = Snapshot::with('categoryValues')->orderByDesc('date')->first();

        if (! $latest instanceof Snapshot) {
            return 0.0;
        }

        return (float) $latest->categoryValues
            ->filter(fn (SnapshotCategoryValue $cv): bool => in_array($cv->category_id, $nonInvestableIds, true))
            ->sum(fn (SnapshotCategoryValue $cv): float => (float) $cv->value);
    }
}
