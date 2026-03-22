<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\Category;
use App\Models\MonthlySnapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Support\Collection;

class BuildStackedBar
{
    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @param  Collection<int, Category>  $categories
     * @return array<int, mixed>
     */
    public function __invoke(Collection $snapshots, Collection $categories): array
    {
        return $snapshots->map(function (MonthlySnapshot $s) use ($categories): array {
            $entry = ['month' => $s->date->format('Y-m-d')];
            foreach ($categories as $cat) {
                /** @var SnapshotCategoryValue|null $cv */
                $cv = $s->categoryValues->firstWhere('category_id', $cat->id);
                $entry[$cat->name] = $cv !== null ? (float) $cv->value : 0.0;
            }

            return $entry;
        })->values()->toArray();
    }
}
