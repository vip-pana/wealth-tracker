<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Contracts\AdvisorProvider;
use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\InvestorProfile;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __construct(
        private readonly AdvisorProvider $provider,
    ) {}

    public function __invoke(): Response
    {
        $profile = InvestorProfile::query()->first();
        $goal = Goal::query()->first();

        // The report is generated on demand (it can take tens of seconds on a
        // local model), so the page loads instantly and only reports whether
        // the advisor is available to generate one. The profile feeds the form;
        // objective/allocation default to the Goal section, shown as a hint so
        // the user knows what they'd inherit by leaving the fields blank.
        return Inertia::render('Advisor', [
            'configured' => $this->provider->isConfigured(),
            'profile' => $profile ? [
                'horizon' => $profile->horizon,
                'risk_tolerance' => $profile->risk_tolerance,
                'objective' => $profile->objective,
                'target_allocation' => $profile->target_allocation,
            ] : null,
            'goalObjective' => $goal?->name,
        ]);
    }
}
