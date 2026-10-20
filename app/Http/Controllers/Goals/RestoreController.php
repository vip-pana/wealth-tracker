<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Controllers\Goals;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;

class RestoreController extends Controller
{
    public function __invoke(int $goal): RedirectResponse
    {
        Goal::onlyTrashed()->findOrFail($goal)->restore();

        return redirect()->route('goal.index')->with('success', 'Obiettivo ripristinato.');
    }
}
