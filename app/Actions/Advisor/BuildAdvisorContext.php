<?php

declare(strict_types=1);

namespace App\Actions\Advisor;

use App\Actions\Action;
use App\Actions\Dashboard\FetchDashboardData;
use App\Models\Goal;
use App\Models\GoalCategoryAllocation;
use App\Models\GoalMilestone;
use App\Models\InvestorProfile;

class BuildAdvisorContext extends Action
{
    public function __construct(
        private readonly FetchDashboardData $fetchDashboardData,
        private readonly ComputeAdvisorExtras $computeExtras,
    ) {}

    /**
     * Assemble the pre-computed metrics the advisor reasons over. Reuses the
     * dashboard's own computation (so the advisor never diverges from what the
     * user sees) and keeps only the analytical slices — portfolio metrics and
     * per-position returns — dropping the chart series the model doesn't need.
     * Includes the investor profile (or null) so the model reasons about the
     * user's actual horizon/risk/goal instead of inventing them.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $dashboard = $this->fetchDashboardData->run();
        $extras = $this->computeExtras->run();

        /** @var array<string, mixed> $portfolio */
        $portfolio = $dashboard['portfolioMetrics'] ?? ['hasData' => false];

        return [
            'portfolio' => $portfolio,
            'positionReturns' => $dashboard['positionReturns'] ?? null,
            'investorProfile' => $this->profile(),
            'goal' => $this->goal($portfolio),
            'contribution' => $extras['contribution'] ?? null,
            'costs' => $extras['costs'] ?? null,
        ];
    }

    /**
     * The psychological side of the user the data can't reveal: horizon, risk
     * tolerance and the free-text synthesis of the risk-profiling interview.
     * The objective and target allocation are NOT here — they live in the Goal
     * (their single source), assembled by goal() below.
     *
     * @return array<string, mixed>|null null only when there's nothing at all
     */
    private function profile(): ?array
    {
        $profile = InvestorProfile::query()->first();

        $horizon = $profile?->horizon;
        $risk = $profile?->risk_tolerance;
        $income = $profile?->income_monthly;
        $emergency = $profile?->emergency_fund;
        $notes = $profile?->notes;

        if ($horizon === null && $risk === null && $income === null && $emergency === null && $notes === null) {
            return null;
        }

        return [
            'horizon' => $horizon,
            'risk_tolerance' => $risk,
            'income_monthly' => $income,
            'emergency_fund' => $emergency,
            'notes' => $notes,
        ];
    }

    /**
     * The objective as the user has already defined it in the Goal section —
     * the single source of truth. Always assembled from the structured columns
     * (name/target_value/target_date + GoalCategoryAllocation) so the advisor
     * sees the real target and never re-asks the user for figures they've set.
     *
     * @param  array<string, mixed>  $portfolio
     * @return array<string, mixed>|null null when no goal exists yet
     */
    private function goal(array $portfolio): ?array
    {
        $goal = Goal::query()->with(['categoryAllocations.category', 'milestones'])->first();

        if (! $goal instanceof Goal) {
            return null;
        }

        $current = is_numeric($portfolio['totalNetWorth'] ?? null) ? (float) $portfolio['totalNetWorth'] : null;
        $target = $goal->target_value > 0.0 ? $goal->target_value : null;

        return [
            'name' => $goal->name,
            'description' => $goal->description,
            'target_value' => $target,
            'target_year' => $goal->target_date?->format('Y'),
            'target_allocation' => $this->targetAllocation($goal),
            'current_value' => $current,
            'remaining' => $target !== null && $current !== null ? max(0.0, $target - $current) : null,
            'milestones' => $this->milestones($goal),
        ];
    }

    /**
     * @return list<array{value: float, year: string, label: string|null}>
     */
    private function milestones(Goal $goal): array
    {
        return array_values($goal->milestones
            ->sortBy('target_value')
            ->map(fn (GoalMilestone $m): array => [
                'value' => $m->target_value,
                'year' => $m->target_date->format('Y'),
                'label' => $m->notes,
            ])
            ->all());
    }

    private function targetAllocation(Goal $goal): ?string
    {
        $parts = $goal->categoryAllocations
            ->map(function (GoalCategoryAllocation $a): string {
                $label = $a->category_id !== null
                    ? ($a->category->name ?? 'Sconosciuta')
                    : ($a->macro_category ?? 'Sconosciuta');

                return $label.' '.rtrim(rtrim(number_format($a->percentage, 1, '.', ''), '0'), '.').'%';
            })
            ->all();

        return $parts === [] ? null : implode(', ', $parts);
    }
}
