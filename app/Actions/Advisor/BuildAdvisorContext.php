<?php

declare(strict_types=1);

namespace App\Actions\Advisor;

use App\Actions\Action;
use App\Actions\Dashboard\FetchDashboardData;
use App\Models\Goal;
use App\Models\GoalCategoryAllocation;
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

        return [
            'portfolio' => $dashboard['portfolioMetrics'] ?? ['hasData' => false],
            'positionReturns' => $dashboard['positionReturns'] ?? null,
            'investorProfile' => $this->profile(),
            'contribution' => $extras['contribution'] ?? null,
            'costs' => $extras['costs'] ?? null,
        ];
    }

    /**
     * The user context the data can't reveal. Horizon and risk tolerance come
     * only from the profile. Objective and target allocation default to the
     * Goal section (their structured home) and are overridden by the profile's
     * free-text fields when filled — each carries its resolved source so the
     * model treats it as real, not inferred.
     *
     * @return array<string, mixed>|null null only when there's nothing at all
     */
    private function profile(): ?array
    {
        $profile = InvestorProfile::query()->first();
        $goal = Goal::query()->with('categoryAllocations.category')->first();

        $objective = $this->resolveObjective($profile?->objective, $goal);
        $allocation = $this->resolveAllocation($profile?->target_allocation, $goal);

        $horizon = $profile?->horizon;
        $risk = $profile?->risk_tolerance;

        if ($horizon === null && $risk === null && $objective === null && $allocation === null) {
            return null;
        }

        return [
            'horizon' => $horizon,
            'risk_tolerance' => $risk,
            'objective' => $objective,
            'target_allocation' => $allocation,
        ];
    }

    /**
     * @return array{value: string, source: string}|null
     */
    private function resolveObjective(?string $override, ?Goal $goal): ?array
    {
        if ($override !== null && $override !== '') {
            return ['value' => $override, 'source' => 'profile'];
        }

        if ($goal !== null) {
            $value = $goal->name;
            if ($goal->target_value > 0.0) {
                $value .= ' (target '.number_format($goal->target_value, 0, ',', '.').'€'
                    .($goal->target_date !== null ? ' entro il '.$goal->target_date->format('Y') : '').')';
            }

            return ['value' => $value, 'source' => 'goal'];
        }

        return null;
    }

    /**
     * @return array{value: string, source: string}|null
     */
    private function resolveAllocation(?string $override, ?Goal $goal): ?array
    {
        if ($override !== null && $override !== '') {
            return ['value' => $override, 'source' => 'profile'];
        }

        if ($goal === null) {
            return null;
        }

        $parts = $goal->categoryAllocations
            ->map(function (GoalCategoryAllocation $a): string {
                $label = $a->category_id !== null
                    ? ($a->category->name ?? 'Sconosciuta')
                    : ($a->macro_category ?? 'Sconosciuta');

                return $label.' '.rtrim(rtrim(number_format($a->percentage, 1, '.', ''), '0'), '.').'%';
            })
            ->all();

        if ($parts === []) {
            return null;
        }

        return ['value' => implode(', ', $parts), 'source' => 'goal'];
    }
}
