<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Actions\Action;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class ComputePosition extends Action
{
    /**
     * Derive an asset's position from its buy/sell history using the
     * weighted-average cost method (the Italian "prezzo medio di carico", and
     * the natural fit for a PAC).
     *
     * Each buy raises the average cost (purchase fees are folded into cost).
     * Each sell removes shares at the *current* average cost, leaving the
     * average unchanged and realising a gain/loss against it. The remaining
     * cost basis is the shares still held times that average.
     *
     * Current value and unrealised P&L are only meaningful with a live price,
     * so they are null when none is given.
     *
     * @param  Collection<int, Transaction>  $transactions  one asset's transactions (any order)
     * @return array{
     *     shares: float,
     *     average_cost: float,
     *     cost_basis: float,
     *     realised_pnl: float,
     *     current_value: float|null,
     *     unrealised_pnl: float|null,
     *     unrealised_pnl_pct: float|null,
     * }
     */
    public function run(Collection $transactions, ?float $livePrice = null): array
    {
        // Process chronologically: the running average must reflect the order
        // buys and sells actually happened.
        $ordered = $transactions->sortBy([
            ['date', 'asc'],
            ['id', 'asc'],
        ]);

        $shares = 0.0;
        $costBasis = 0.0;   // total cost of the shares currently held
        $realisedPnl = 0.0;

        foreach ($ordered as $tx) {
            $fees = (float) ($tx->fees ?? 0.0);

            if ($tx->type === Transaction::TYPE_BUY) {
                $shares += $tx->shares;
                $costBasis += $tx->shares * $tx->price_per_share + $fees;

                continue;
            }

            // Sell: remove shares at the current average cost. Selling more
            // than held should not happen, but clamp so the position can never
            // go negative and corrupt the average.
            if ($shares <= 0.0) {
                continue;
            }

            $averageCost = $costBasis / $shares;
            $soldShares = min($tx->shares, $shares);

            $proceeds = $soldShares * $tx->price_per_share - $fees;
            $realisedPnl += $proceeds - $soldShares * $averageCost;

            $shares -= $soldShares;
            $costBasis -= $soldShares * $averageCost;
        }

        // Guard against floating-point dust leaving a sliver of shares/cost
        // after a full exit.
        if ($shares < 1e-9) {
            $shares = 0.0;
            $costBasis = 0.0;
        }

        $averageCost = $shares > 0.0 ? $costBasis / $shares : 0.0;

        $currentValue = $livePrice !== null ? $shares * $livePrice : null;
        $unrealisedPnl = $currentValue !== null ? $currentValue - $costBasis : null;
        $unrealisedPnlPct = $unrealisedPnl !== null && $costBasis > 0.0
            ? $unrealisedPnl / $costBasis * 100
            : null;

        return [
            'shares' => round($shares, 8),
            'average_cost' => round($averageCost, 8),
            'cost_basis' => round($costBasis, 2),
            'realised_pnl' => round($realisedPnl, 2),
            'current_value' => $currentValue !== null ? round($currentValue, 2) : null,
            'unrealised_pnl' => $unrealisedPnl !== null ? round($unrealisedPnl, 2) : null,
            'unrealised_pnl_pct' => $unrealisedPnlPct !== null ? round($unrealisedPnlPct, 2) : null,
        ];
    }
}
