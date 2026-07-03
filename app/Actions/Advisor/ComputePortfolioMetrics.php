<?php

declare(strict_types=1);

namespace App\Actions\Advisor;

use App\Actions\Action;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Snapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Support\Collection;

class ComputePortfolioMetrics extends Action
{
    /**
     * Compute hard portfolio metrics for the AI advisor to interpret.
     *
     * Operates purely on the data the app already has (monthly liquid
     * snapshots + the goal). No live prices, no AI: just arithmetic the
     * LLM should never be trusted to do itself.
     *
     * @param  Collection<int, Snapshot>  $monthlySnapshots  one liquid snapshot per month, date-ordered
     * @param  Collection<int, Category>  $categories  the liquid categories
     * @return array<string, mixed>
     */
    public function run(Collection $monthlySnapshots, Collection $categories, ?Goal $goal): array
    {
        $ordered = $monthlySnapshots->values();

        /** @var Snapshot|null $latest */
        $latest = $ordered->last();

        if (! $latest instanceof Snapshot || $latest->total_value <= 0.0) {
            return ['hasData' => false];
        }

        $total = (float) $latest->total_value;

        $allocation = $this->allocation($latest, $categories, $total);

        return [
            'hasData' => true,
            'monthsTracked' => $ordered->count(),
            'totalNetWorth' => round($total, 2),
            'allocation' => $allocation,
            'allocationDrift' => $this->allocationDrift($ordered, $categories),
            'concentration' => $this->concentration($allocation),
            'liquidity' => $this->liquidity($allocation),
            'volatility' => $this->volatility($ordered),
            'goalEta' => $this->goalEta($ordered, $goal, $total),
        ];
    }

    /**
     * Current allocation per category as { name, value, share_pct },
     * sorted largest-first.
     *
     * @param  Collection<int, Category>  $categories
     * @return list<array{name: string, value: float, share_pct: float}>
     */
    private function allocation(Snapshot $latest, Collection $categories, float $total): array
    {
        $names = $this->categoryNames($categories);

        $rows = $latest->categoryValues
            ->map(fn (SnapshotCategoryValue $cv): array => [
                'name' => $names[$cv->category_id] ?? 'Sconosciuta',
                'value' => round((float) $cv->value, 2),
                'share_pct' => round((float) $cv->value / $total * 100, 2),
            ])
            ->sortByDesc('share_pct')
            ->all();

        return array_values($rows);
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array<int, string>
     */
    private function categoryNames(Collection $categories): array
    {
        $names = [];
        foreach ($categories as $category) {
            $names[$category->id] = $category->name;
        }

        return $names;
    }

    /**
     * How each category's share has shifted vs. the earliest tracked month,
     * in percentage points. Surfaces "you've drifted heavily into X".
     *
     * @param  Collection<int, Snapshot>  $ordered
     * @param  Collection<int, Category>  $categories
     * @return list<array{name: string, share_pct: float, share_pct_then: float, delta_pp: float}>
     */
    private function allocationDrift(Collection $ordered, Collection $categories): array
    {
        /** @var Snapshot $first */
        $first = $ordered->first();
        /** @var Snapshot $latest */
        $latest = $ordered->last();

        if ($first->id === $latest->id) {
            return [];
        }

        $names = $this->categoryNames($categories);
        $thenShares = $this->sharesByCategory($first);
        $nowShares = $this->sharesByCategory($latest);

        $rows = collect(array_keys($thenShares + $nowShares))
            ->map(function (int $id) use ($names, $thenShares, $nowShares): array {
                $then = $thenShares[$id] ?? 0.0;
                $now = $nowShares[$id] ?? 0.0;

                return [
                    'name' => $names[$id] ?? 'Sconosciuta',
                    'share_pct' => round($now, 2),
                    'share_pct_then' => round($then, 2),
                    'delta_pp' => round($now - $then, 2),
                ];
            })
            ->sortByDesc(fn (array $row): float => abs($row['delta_pp']))
            ->all();

        return array_values($rows);
    }

    /**
     * @return array<int, float> category_id => share percentage of that snapshot
     */
    private function sharesByCategory(Snapshot $snapshot): array
    {
        $total = (float) $snapshot->total_value;

        if ($total <= 0.0) {
            return [];
        }

        $shares = [];
        foreach ($snapshot->categoryValues as $cv) {
            $shares[$cv->category_id] = (float) $cv->value / $total * 100;
        }

        return $shares;
    }

    /**
     * Concentration of the portfolio: the Herfindahl-Hirschman Index over
     * category shares (0–10000; higher = more concentrated) plus the single
     * largest category. A low HHI means well spread; a high one means most
     * of the net worth rides on one bucket.
     *
     * @param  list<array{name: string, value: float, share_pct: float}>  $allocation
     * @return array{hhi: float, top_category: string, top_share_pct: float}
     */
    private function concentration(array $allocation): array
    {
        $hhi = 0.0;
        foreach ($allocation as $slice) {
            $hhi += $slice['share_pct'] ** 2;
        }

        $top = $allocation[0] ?? ['name' => '—', 'share_pct' => 0.0];

        return [
            'hhi' => round($hhi, 1),
            'top_category' => $top['name'],
            'top_share_pct' => $top['share_pct'],
        ];
    }

    /**
     * Cash sitting in liquid "Liquidità" categories: its share and value.
     * Lets the advisor flag idle cash losing ground to inflation.
     *
     * @param  list<array{name: string, value: float, share_pct: float}>  $allocation
     * @return array{value: float, share_pct: float}
     */
    private function liquidity(array $allocation): array
    {
        $cash = array_filter(
            $allocation,
            fn (array $slice): bool => $slice['name'] === 'Liquidità',
        );

        return [
            'value' => round((float) array_sum(array_column($cash, 'value')), 2),
            'share_pct' => round((float) array_sum(array_column($cash, 'share_pct')), 2),
        ];
    }

    /**
     * Month-over-month return volatility: the standard deviation of the
     * monthly percentage changes in net worth. A proxy for how bumpy the
     * ride has been; null until there are at least three months.
     *
     * @param  Collection<int, Snapshot>  $ordered
     * @return array{monthly_stddev_pct: float|null, best_month_pct: float|null, worst_month_pct: float|null}
     */
    private function volatility(Collection $ordered): array
    {
        $returns = [];
        $prev = null;

        foreach ($ordered as $s) {
            if ($prev !== null && (float) $prev->total_value > 0.0) {
                $returns[] = ((float) $s->total_value - (float) $prev->total_value) / (float) $prev->total_value * 100;
            }
            $prev = $s;
        }

        if (count($returns) < 2) {
            return ['monthly_stddev_pct' => null, 'best_month_pct' => null, 'worst_month_pct' => null];
        }

        $mean = array_sum($returns) / count($returns);
        $variance = array_sum(array_map(fn (float $r): float => ($r - $mean) ** 2, $returns)) / count($returns);

        return [
            'monthly_stddev_pct' => round(sqrt($variance), 2),
            'best_month_pct' => round(max($returns), 2),
            'worst_month_pct' => round(min($returns), 2),
        ];
    }

    /**
     * Projected goal arrival at the current pace. The pace is the slope of a
     * least-squares regression over the tracked points (the same line the
     * dashboard forecast draws), not first-vs-last: weighting every point
     * keeps a single noisy month from flipping the trajectory. Reports whether
     * the goal is already reached, the months/date to reach it, whether the
     * trajectory beats the goal's own target date, and a low-confidence flag
     * when there are too few months for the estimate to be trustworthy.
     *
     * @param  Collection<int, Snapshot>  $ordered
     * @return array<string, mixed>|null null when there's no goal or not enough data
     */
    private function goalEta(Collection $ordered, ?Goal $goal, float $total): ?array
    {
        if (! $goal instanceof Goal || $ordered->count() < 2) {
            return null;
        }

        $target = (float) $goal->target_value;
        $lowConfidence = $ordered->count() < 4;

        if ($total >= $target) {
            return ['reached' => true, 'target_value' => round($target, 2)];
        }

        $monthlyGain = $this->monthlyTrendGain($ordered);

        if ($monthlyGain === null) {
            return null;
        }

        if ($monthlyGain <= 0.0) {
            return [
                'reached' => false,
                'target_value' => round($target, 2),
                'on_track' => false,
                'avg_monthly_gain' => round($monthlyGain, 2),
                'low_confidence' => $lowConfidence,
            ];
        }

        /** @var Snapshot $latest */
        $latest = $ordered->last();

        $monthsToGo = (int) ceil(($target - $total) / $monthlyGain);
        $eta = $latest->date->copy()->addMonths($monthsToGo);

        return [
            'reached' => false,
            'target_value' => round($target, 2),
            'avg_monthly_gain' => round($monthlyGain, 2),
            'months_to_goal' => $monthsToGo,
            'projected_date' => $eta->format('Y-m-d'),
            'target_date' => $goal->target_date?->format('Y-m-d'),
            'on_track' => $goal->target_date !== null
                ? $eta->lessThanOrEqualTo($goal->target_date)
                : null,
            'low_confidence' => $lowConfidence,
        ];
    }

    /**
     * Net-worth gain per average month from the least-squares slope over the
     * tracked points (x = days from the first snapshot, y = total value). The
     * daily slope is scaled to an average month (30.44 days). Returns null when
     * the regression is degenerate (all points on the same day).
     *
     * @param  Collection<int, Snapshot>  $ordered
     */
    private function monthlyTrendGain(Collection $ordered): ?float
    {
        /** @var Snapshot $first */
        $first = $ordered->first();
        $origin = $first->date->copy()->startOfDay();

        $xs = [];
        $ys = [];
        foreach ($ordered as $s) {
            $xs[] = (float) $origin->diffInDays($s->date);
            $ys[] = (float) $s->total_value;
        }

        $n = count($xs);
        $sumX = array_sum($xs);
        $sumY = array_sum($ys);
        $sumXY = 0.0;
        $sumX2 = 0.0;
        foreach ($xs as $i => $x) {
            $sumXY += $x * $ys[$i];
            $sumX2 += $x * $x;
        }

        $denominator = $n * $sumX2 - $sumX ** 2;
        if ($denominator === 0.0) {
            return null;
        }

        $dailySlope = ($n * $sumXY - $sumX * $sumY) / $denominator;

        return $dailySlope * 30.44;
    }
}
