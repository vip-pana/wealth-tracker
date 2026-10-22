<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

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
