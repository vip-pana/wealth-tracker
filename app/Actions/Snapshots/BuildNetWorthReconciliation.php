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

        /** @var Collection<int, string> $names */
        $names = Category::query()->pluck('name', 'id');

        $currentMonthTotal = 0.0;
        $carriedForward = [];

        foreach ($byCategory as $categoryId => $value) {
            if (str_starts_with($asOf[$categoryId], $referenceMonth)) {
                $currentMonthTotal += $value;

                continue;
            }

            $carriedForward[] = [
                'category' => $names->get($categoryId, ''),
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
