<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;

class RestoreController extends Controller
{
    public function __invoke(int $asset): RedirectResponse
    {
        Asset::onlyTrashed()->findOrFail($asset)->restore();

        return redirect()->back()->with('success', 'Asset ripristinato.');
    }
}
