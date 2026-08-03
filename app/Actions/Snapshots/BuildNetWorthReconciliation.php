<?php

declare(strict_types=1);

namespace App\Actions\Snapshots;

use App\Actions\Action;
use App\Models\Category;
use Illuminate\Support\Collection;

class BuildNetWorthReconciliation extends Action
{
    public function __construct(
        private readonly ComputeValuesAsOf $computeValuesAsOf,
    ) {}

    /**
     * Explain how the "as of $date" net worth splits between the reference month
     * and categories carried forward from earlier months. A category with no row
     * in $referenceMonth still counts toward net worth using its last known
     * value, so this itemises those carried-forward categories to reconcile the
     * snapshot total against the sum of the reference month's rows.
     *
     * Illiquid categories are left out entirely — `total` here is therefore the
     * liquid net worth, not the full figure `ComputeValuesAsOf` returns (which
     * still includes them, and is what StoreSnapshot persists).
     *
     * @return array{
     *     total: float,
     *     currentMonthTotal: float,
     *     carriedForwardTotal: float,
     *     carriedForward: list<array{category: string, value: float, asOf: string}>
     * }
     */
    public function run(string $date, string $referenceMonth): array
    {
        ['byCategory' => $byCategory, 'total' => $total, 'asOf' => $asOf] = $this->computeValuesAsOf->run($date);

        /** @var Collection<int, Category> $categories */
        $categories = Category::query()->get()->keyBy('id');

        $currentMonthTotal = 0.0;
        $carriedForward = [];

        foreach ($byCategory as $categoryId => $value) {
            $category = $categories->get($categoryId);

            // Illiquid categories are excluded from the Bilancio everywhere else
            // (FetchInputData, FetchAssetsByMonth), so drop them here too — both
            // from the itemised rows and from the total they reconcile against.
            if ($category === null || ($category->macro_category?->isIlliquid() ?? false)) {
                $total -= $value;

                continue;
            }

            if (str_starts_with($asOf[$categoryId], $referenceMonth)) {
                $currentMonthTotal += $value;

                continue;
            }

            $carriedForward[] = [
                'category' => $category->name,
                'value' => $value,
                'asOf' => $asOf[$categoryId],
            ];
        }

        return [
            'total' => $total,
            'currentMonthTotal' => $currentMonthTotal,
            'carriedForwardTotal' => $total - $currentMonthTotal,
            'carriedForward' => $carriedForward,
        ];
    }
}
