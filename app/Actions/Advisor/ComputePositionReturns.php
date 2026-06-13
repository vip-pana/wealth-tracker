<?php

declare(strict_types=1);

namespace App\Actions\Advisor;

use App\Actions\Action;
use App\Actions\Transactions\ComputePosition;
use App\Models\Asset;
use App\Models\AssetPrice;

class ComputePositionReturns extends Action
{
    public function __construct(
        private readonly ComputePosition $computePosition,
    ) {}

    /**
     * True return of the share positions driven by imported transactions —
     * the figure that was impossible before transactions: how much the market
     * gave back net of what was contributed. For each transaction-managed
     * asset it derives cost basis, current value (shares × live price),
     * unrealised P&L (open positions) and realised P&L (from sells); it also
     * sums these into a portfolio-wide aggregate.
     *
     * Returns null when no asset is transaction-managed, so callers can omit
     * the section entirely rather than show empty zeros.
     *
     * @return array{
     *     positions: list<array{id: int, name: string, shares: float, average_cost: float, cost_basis: float, current_value: float|null, unrealised_pnl: float|null, unrealised_pnl_pct: float|null, realised_pnl: float}>,
     *     aggregate: array{cost_basis: float, current_value: float, unrealised_pnl: float, unrealised_pnl_pct: float|null, realised_pnl: float},
     * }|null
     */
    public function run(): ?array
    {
        $assets = Asset::query()
            ->has('transactions')
            ->with('transactions')
            ->orderByDesc('date')
            ->get()
            ->unique('isin');

        if ($assets->isEmpty()) {
            return null;
        }

        $prices = AssetPrice::all()->keyBy('ticker');

        $positions = [];
        $totalCost = 0.0;
        $totalValue = 0.0;
        $totalRealised = 0.0;

        foreach ($assets as $asset) {
            /** @var AssetPrice|null $priceRecord */
            $priceRecord = $asset->ticker !== null ? $prices->get($asset->ticker) : null;
            $livePrice = $priceRecord?->price;

            $position = $this->computePosition->run(
                $asset->transactions,
                is_numeric($livePrice) ? (float) $livePrice : null,
            );

            $positions[] = [
                'id' => $asset->id,
                'name' => $asset->name,
                'shares' => $position['shares'],
                'average_cost' => $position['average_cost'],
                'cost_basis' => $position['cost_basis'],
                'current_value' => $position['current_value'],
                'unrealised_pnl' => $position['unrealised_pnl'],
                'unrealised_pnl_pct' => $position['unrealised_pnl_pct'],
                'realised_pnl' => $position['realised_pnl'],
            ];

            $totalCost += $position['cost_basis'];
            $totalRealised += $position['realised_pnl'];

            // Only positions we could price feed the aggregate value/unrealised
            // P&L; an unpriced one still contributes its cost and realised P&L.
            if ($position['current_value'] !== null) {
                $totalValue += $position['current_value'];
            }
        }

        $totalUnrealised = $totalValue - $totalCost;

        return [
            'positions' => $positions,
            'aggregate' => [
                'cost_basis' => round($totalCost, 2),
                'current_value' => round($totalValue, 2),
                'unrealised_pnl' => round($totalUnrealised, 2),
                'unrealised_pnl_pct' => $totalCost > 0.0 ? round($totalUnrealised / $totalCost * 100, 2) : null,
                'realised_pnl' => round($totalRealised, 2),
            ],
        ];
    }
}
