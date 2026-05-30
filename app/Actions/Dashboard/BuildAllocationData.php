<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Action;
use App\Models\Category;
use App\Models\Snapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Support\Collection;

class BuildAllocationData extends Action
{
    /**
     * @param  Collection<int, Snapshot>  $snapshots
     * @param  Collection<int, Category>  $categories
     * @return array<int, mixed>
     */
    public function run(Collection $snapshots, Collection $categories): array
    {
        $last = $snapshots->last();
        if (! $last) {
            return [];
        }

        return $last->categoryValues->map(fn (SnapshotCategoryValue $cv): array => [
            'name' => $cv->category->name,
            'value' => (float) $cv->value,
            'color' => $cv->category->color,
        ])->values()->toArray();
    }
}
