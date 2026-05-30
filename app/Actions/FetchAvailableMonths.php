<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Asset;

class FetchAvailableMonths extends Action
{
    /** @return list<string> */
    public function run(): array
    {
        /** @var list<string> */
        return Asset::selectRaw("strftime('%Y-%m-01', date) as month")
            ->groupByRaw("strftime('%Y-%m', date)")
            ->orderByDesc('month')
            ->pluck('month')
            ->all();
    }
}
