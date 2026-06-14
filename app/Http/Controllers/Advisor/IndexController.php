<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Contracts\AdvisorProvider;
use App\Http\Controllers\Controller;
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

        // The report is generated on demand (it can take tens of seconds on a
        // local model), so the page loads instantly and only reports whether
        // the advisor is available to generate one. The profile feeds the form.
        return Inertia::render('Advisor', [
            'configured' => $this->provider->isConfigured(),
            'profile' => $profile ? [
                'horizon' => $profile->horizon,
                'risk_tolerance' => $profile->risk_tolerance,
                'objective' => $profile->objective,
                'target_allocation' => $profile->target_allocation,
            ] : null,
        ]);
    }
}
