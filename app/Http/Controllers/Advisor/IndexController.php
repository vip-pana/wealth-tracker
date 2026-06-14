<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Contracts\AdvisorProvider;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __construct(
        private readonly AdvisorProvider $provider,
    ) {}

    public function __invoke(): Response
    {
        // The report is generated on demand (it can take tens of seconds on a
        // local model), so the page loads instantly and only reports whether
        // the advisor is available to generate one.
        return Inertia::render('Advisor', [
            'configured' => $this->provider->isConfigured(),
        ]);
    }
}
