<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Actions\Action;
use App\Models\BankTransaction;

class ComputeMonthlyExpense extends Action
{
    /**
     * Average monthly expense over all history: the magnitude of the
     * non-excluded expense rows divided by the number of calendar months the
     * transactions span (min 1). Transfers and excluded rows don't count.
     * Mirrors the Cashflow page's whole-history average, which the
     * emergency-fund coverage divides by. Returns null when no expense has been
     * observed, so callers can tell "no data yet" from zero.
     */
    public function run(): ?float
    {
        $rows = BankTransaction::query()
            ->where('flow_type', BankTransaction::FLOW_EXPENSE)
            ->where('excluded', false)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $total = 0.0;
        $min = null;
        $max = null;

        foreach ($rows as $row) {
            $total += abs($row->amount);
            $date = $row->booking_date->format('Y-m');
            $min = $min === null || $date < $min ? $date : $min;
            $max = $max === null || $date > $max ? $date : $max;
        }

        $months = $this->monthsBetween((string) $min, (string) $max);

        return round($total / $months, 2);
    }

    private function monthsBetween(string $from, string $to): int
    {
        [$fy, $fm] = array_map(intval(...), explode('-', $from));
        [$ty, $tm] = array_map(intval(...), explode('-', $to));

        return max(1, ($ty - $fy) * 12 + ($tm - $fm) + 1);
    }
}
