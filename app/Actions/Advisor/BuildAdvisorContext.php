<?php

declare(strict_types=1);

namespace App\Actions\Advisor;

use App\Actions\Action;
use App\Actions\Dashboard\FetchDashboardData;
use App\Models\InvestorProfile;

class BuildAdvisorContext extends Action
{
    public function __construct(
        private readonly FetchDashboardData $fetchDashboardData,
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

        return [
            'portfolio' => $dashboard['portfolioMetrics'] ?? ['hasData' => false],
            'positionReturns' => $dashboard['positionReturns'] ?? null,
            'investorProfile' => $this->profile(),
        ];
    }

    /**
     * @return array<string, string|null>|null
     */
    private function profile(): ?array
    {
        $profile = InvestorProfile::query()->first();

        if ($profile === null) {
            return null;
        }

        return [
            'horizon' => $profile->horizon,
            'risk_tolerance' => $profile->risk_tolerance,
            'objective' => $profile->objective,
            'target_allocation' => $profile->target_allocation,
        ];
    }
}
