<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Controllers\Goals;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;

class DestroyController extends Controller
{
    public function __invoke(Goal $goal): RedirectResponse
    {
        $goal->delete();

        return redirect()->route('goal.index')
            ->with('success', 'Obiettivo eliminato.')
            ->with('undo', route('goal.restore', $goal->id, absolute: false));
    }
}
