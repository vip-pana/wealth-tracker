<?php

declare(strict_types=1);

namespace App\Actions\Advisor;

use App\Actions\Action;
use App\Models\Asset;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class ComputeAdvisorExtras extends Action
{
    /** Trailing window for the average monthly contribution. */
    private const CONTRIBUTION_MONTHS = 6;

    /**
     * Extra advisor inputs the base metrics don't cover, both pure arithmetic
     * the LLM must never do itself:
     *  - contribution: the average monthly amount invested via the savings plan
     *    (PAC), DERIVED from transactions — not asked of the user.
     *  - costs: the value-weighted TER and the resulting yearly cost in euro,
     *    so the advisor can reason about cost drag on net return.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return [
            'contribution' => $this->contribution(),
            'costs' => $this->costs(),
        ];
    }

    /**
     * Average monthly savings-plan (PAC) contribution over the trailing window.
     * Sums buy transactions tagged as savings_plan and divides by the window so
     * a partial month doesn't inflate the figure. Null when there's no PAC.
     *
     * @return array<string, mixed>|null
     */
    private function contribution(): ?array
    {
        $since = Carbon::today()->startOfMonth()->subMonths(self::CONTRIBUTION_MONTHS - 1);

        $txns = Transaction::query()
            ->where('type', Transaction::TYPE_BUY)
            ->where('source', Transaction::SOURCE_SAVINGS_PLAN)
            ->where('date', '>=', $since->format('Y-m-d'))
            ->get();

        if ($txns->isEmpty()) {
            return null;
        }

        $total = $txns->sum(fn (Transaction $t): float => $t->shares * $t->price_per_share + ($t->fees ?? 0.0));

        return [
            'monthly_avg' => round((float) $total / self::CONTRIBUTION_MONTHS, 2),
            'months' => self::CONTRIBUTION_MONTHS,
        ];
    }

    /**
     * Value-weighted expense ratio and the yearly cost it implies, over the
     * assets that carry a TER. Also reports how much of the portfolio's value
     * has a TER set, so the advisor can flag thin coverage instead of trusting
     * a figure built from a sliver. Null when no asset has a TER.
     *
     * @return array<string, mixed>|null
     */
    private function costs(): ?array
    {
        $currentMonth = Carbon::today()->format('Y-m-01');

        $assets = Asset::query()
            ->where('date', $currentMonth)
            ->whereNotNull('expense_ratio')
            ->get();

        if ($assets->isEmpty()) {
            return null;
        }

        $coveredValue = 0.0;
        $annualCost = 0.0;

        foreach ($assets as $asset) {
            $value = $asset->currentValue();
            $ter = (float) $asset->expense_ratio;
            $coveredValue += $value;
            $annualCost += $value * $ter / 100.0;
        }

        if ($coveredValue <= 0.0) {
            return null;
        }

        return [
            'weighted_ter_pct' => round($annualCost / $coveredValue * 100.0, 3),
            'annual_cost' => round($annualCost, 2),
            'covered_value' => round($coveredValue, 2),
        ];
    }
}
