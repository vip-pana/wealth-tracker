<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\Category;
use App\Models\MonthlySnapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Support\Collection;

class ComputeMonthComparison
{
    /**
     * @param  Collection<int, MonthlySnapshot>  $snapshots
     * @param  Collection<int, Category>  $categories
     * @return array<int, mixed>
     */
    public function __invoke(Collection $snapshots, Collection $categories): array
    {
        if ($snapshots->count() < 2) {
            return [];
        }

        $last = $snapshots->last();
        $penult = $snapshots->slice(-2, 1)->first();

        if (! $last instanceof MonthlySnapshot || ! $penult instanceof MonthlySnapshot) {
            return [];
        }

        return $categories->map(function (Category $cat) use ($last, $penult): array {
            /** @var SnapshotCategoryValue|null $lastCv */
            $lastCv = $last->categoryValues->firstWhere('category_id', $cat->id);
            /** @var SnapshotCategoryValue|null $penultCv */
            $penultCv = $penult->categoryValues->firstWhere('category_id', $cat->id);

            return [
                'category' => $cat->name,
                'color' => $cat->color,
                'current' => $lastCv !== null ? (float) $lastCv->value : 0.0,
                'previous' => $penultCv !== null ? (float) $penultCv->value : 0.0,
            ];
        })->values()->toArray();
    }
}
