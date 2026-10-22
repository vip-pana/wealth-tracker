<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\AdvisorSession;
use Illuminate\Http\RedirectResponse;

class DestroyController extends Controller
{
    public function __invoke(AdvisorSession $session): RedirectResponse
    {
        // Messages cascade-delete with the session (FK onDelete cascade).
        $session->delete();

        return redirect()->route('advisor.index');
    }
}
