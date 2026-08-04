<?php

declare(strict_types=1);

namespace App\Actions\Snapshots;

use App\Actions\Action;
use App\Models\Category;
use App\Models\Snapshot;
use Illuminate\Support\Collection;

class BuildSnapshotDiff extends Action
{
    public function __construct(
        private readonly ComputeValuesAsOf $computeValuesAsOf,
    ) {}

    /**
     * What a new snapshot taken now would change, compared category by category
     * against the most recent existing snapshot — the same reference
     * ResolveSnapshotState uses to call a month "stale", so this explains that
     * badge rather than answering a different question.
     *
     * Illiquid categories are left out, matching the rest of the Bilancio page.
     * The snapshot itself still stores them, so `total` here is the liquid
     * figure and can differ from the stored `total_value`.
     *
     * Null when there is no snapshot to compare against.
     *
     * @return array{
     *     snapshotDate: string,
     *     rows: list<array{categoryId: int, category: string, color: string, previous: float, current: float, delta: float}>,
     *     previousTotal: float,
     *     currentTotal: float
     * }|null
     */
    public function run(string $date): ?array
    {
        $snapshot = Snapshot::with('categoryValues')->orderByDesc('date')->first();

        if ($snapshot === null) {
            return null;
        }

        /** @var Collection<int, Category> $categories */
        $categories = Category::query()
            ->get()
            ->reject(fn (Category $c): bool => $c->macro_category?->isIlliquid() ?? false)
            ->keyBy('id');

        $previousByCategory = $snapshot->categoryValues
            ->mapWithKeys(fn ($cv): array => [$cv->category_id => (float) $cv->value])
            ->all();

        ['byCategory' => $currentByCategory] = $this->computeValuesAsOf->run($date);

        // A category counts if either side knows about it: one that has just
        // appeared has no previous value, one that was emptied has no current
        // one, and both are changes worth showing.
        $categoryIds = array_unique([
            ...array_keys($previousByCategory),
            ...array_keys($currentByCategory),
        ]);

        $rows = [];
        $previousTotal = 0.0;
        $currentTotal = 0.0;

        foreach ($categoryIds as $categoryId) {
            $category = $categories->get($categoryId);

            if ($category === null) {
                continue;
            }

            $previous = $previousByCategory[$categoryId] ?? 0.0;
            $current = $currentByCategory[$categoryId] ?? 0.0;

            $previousTotal += $previous;
            $currentTotal += $current;

            $rows[] = [
                'categoryId' => $category->id,
                'category' => $category->name,
                'color' => $category->color,
                'previous' => $previous,
                'current' => $current,
                'delta' => $current - $previous,
            ];
        }

        usort($rows, fn (array $a, array $b): int => abs($b['delta']) <=> abs($a['delta']));

        return [
            'snapshotDate' => $snapshot->date->format('Y-m-d'),
            'rows' => $rows,
            'previousTotal' => $previousTotal,
            'currentTotal' => $currentTotal,
        ];
    }
}
