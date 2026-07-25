<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Actions\Action;
use App\Models\BankTransaction;

class ComputeMonthlySalary extends Action
{
    /**
     * The observed net monthly salary: the sum of salary credits divided by the
     * number of distinct months that actually received one — not by the whole
     * history span, so a month with no salary (or the current, not-yet-paid
     * month) doesn't drag the figure down. Mirrors the Cashflow page's card.
     *
     * A salary credit is an income row whose note carries a "STIPENDIO" marker.
     * Excluded rows don't count. Returns null when no salary has been observed,
     * so callers can tell "no data yet" from "zero".
     */
    public function run(): ?float
    {
        $rows = BankTransaction::query()
            ->where('flow_type', BankTransaction::FLOW_INCOME)
            ->where('excluded', false)
            ->get();

        $total = 0.0;
        /** @var array<string, true> $months */
        $months = [];

        foreach ($rows as $row) {
            if (stripos($this->note($row), 'STIPENDIO') === false) {
                continue;
            }

            $total += $row->amount;
            $months[$row->booking_date->format('Y-m')] = true;
        }

        if ($months === []) {
            return null;
        }

        return round($total / count($months), 2);
    }

    private function note(BankTransaction $row): string
    {
        $remittance = $row->raw['remittance_information'] ?? null;

        if (is_array($remittance)) {
            return implode(' ', array_map(fn (mixed $p): string => is_scalar($p) ? (string) $p : '', $remittance));
        }

        return is_string($remittance) ? $remittance : '';
    }
}
