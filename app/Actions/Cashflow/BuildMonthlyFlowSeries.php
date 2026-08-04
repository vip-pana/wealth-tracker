<?php

declare(strict_types=1);

namespace App\Actions\Cashflow;

use App\Actions\Action;
use App\Models\BankTransaction;
use Illuminate\Contracts\Database\Query\Builder;

class BuildMonthlyFlowSeries extends Action
{
    /**
     * Income, expense and net per month over all history, oldest first, so the
     * page can say whether the shown month is normal or an outlier.
     *
     * The filters mirror the Cashflow page's client-side month totals — internal
     * transfers and the rows you marked "escludi" don't count, and an
     * unclassified row counts as an expense just as `effective()` does there —
     * so the last point matches the net shown for that month. Only the saved
     * state is seen: the client recomputes the current month from staged edits,
     * this doesn't.
     *
     * @return list<array{date: string, income: float, expense: float, net: float}>
     */
    public function run(): array
    {
        $rows = BankTransaction::query()
            ->toBase()
            ->where('excluded', false)
            // Not `!= transfer`: that would drop the NULL (unclassified) rows,
            // which the page counts as expenses.
            ->where(fn (Builder $q): Builder => $q
                ->where('flow_type', '!=', BankTransaction::FLOW_TRANSFER)
                ->orWhereNull('flow_type'))
            ->whereNull('deleted_at')
            ->selectRaw("strftime('%Y-%m-01', booking_date) as month")
            ->selectRaw('sum(case when flow_type = ? then amount else 0 end) as income', [BankTransaction::FLOW_INCOME])
            ->selectRaw('sum(case when flow_type = ? or flow_type is null then amount else 0 end) as expense', [BankTransaction::FLOW_EXPENSE])
            ->groupByRaw("strftime('%Y-%m', booking_date)")
            ->orderBy('month')
            ->get();

        $series = [];

        foreach ($rows as $row) {
            $income = round((float) $row->income, 2);
            $expense = round((float) $row->expense, 2);

            $series[] = [
                'date' => (string) $row->month,
                'income' => $income,
                'expense' => $expense,
                'net' => round($income + $expense, 2),
            ];
        }

        return $series;
    }
}
