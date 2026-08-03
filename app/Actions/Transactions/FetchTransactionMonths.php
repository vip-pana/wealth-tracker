<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Actions\Action;
use App\Models\BankTransaction;

class FetchTransactionMonths extends Action
{
    /**
     * Months that hold at least one bank transaction, newest first, each pinned
     * to the 1st so it can be used directly as the `month` query param.
     *
     * @return list<string>
     */
    public function run(): array
    {
        /** @var list<string> */
        return BankTransaction::selectRaw("strftime('%Y-%m-01', booking_date) as month")
            ->groupByRaw("strftime('%Y-%m', booking_date)")
            ->orderByDesc('month')
            ->pluck('month')
            ->all();
    }
}
