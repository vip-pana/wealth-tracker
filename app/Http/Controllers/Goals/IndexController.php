<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Controllers\Goals;

use App\Actions\Goals\FetchGoalData;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __construct(
        private readonly FetchGoalData $fetchGoalData,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Goal', $this->fetchGoalData->run());
    }
}
