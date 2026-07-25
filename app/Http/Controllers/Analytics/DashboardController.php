<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Actions\Dashboard\FetchDashboardData;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly FetchDashboardData $fetchDashboardData,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', $this->fetchDashboardData->run());
    }
}
