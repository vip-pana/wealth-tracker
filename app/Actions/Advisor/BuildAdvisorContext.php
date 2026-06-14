<?php

declare(strict_types=1);

namespace App\Actions\Advisor;

use App\Actions\Action;
use App\Actions\Dashboard\FetchDashboardData;

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
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $dashboard = $this->fetchDashboardData->run();

        return [
            'portfolio' => $dashboard['portfolioMetrics'] ?? ['hasData' => false],
            'positionReturns' => $dashboard['positionReturns'] ?? null,
        ];
    }
}
