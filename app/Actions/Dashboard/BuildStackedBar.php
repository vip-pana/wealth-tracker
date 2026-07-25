<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Action;
use App\Models\Category;
use App\Models\Snapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Support\Collection;

class BuildStackedBar extends Action
{
    /**
     * @param  Collection<int, Snapshot>  $snapshots
     * @param  Collection<int, Category>  $categories
     * @return array<int, mixed>
     */
    public function run(Collection $snapshots, Collection $categories): array
    {
        return $snapshots->map(function (Snapshot $s) use ($categories): array {
            $entry = ['date' => $s->date->format('Y-m-d')];
            foreach ($categories as $cat) {
                /** @var SnapshotCategoryValue|null $cv */
                $cv = $s->categoryValues->firstWhere('category_id', $cat->id);
                $entry[$cat->name] = $cv !== null ? (float) $cv->value : 0.0;
            }

            return $entry;
        })->values()->toArray();
    }
}
