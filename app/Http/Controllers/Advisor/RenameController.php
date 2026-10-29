<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\AdvisorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RenameController extends Controller
{
    public function __invoke(Request $request, AdvisorSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

        $session->update(['title' => $validated['title']]);

        return redirect()->route('advisor.show', $session);
    }
}
