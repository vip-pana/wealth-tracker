<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;

class ReadController extends Controller
{
    public function __invoke(Notification $notification): RedirectResponse
    {
        $notification->markRead();

        return redirect()->back();
    }
}
