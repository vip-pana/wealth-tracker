<?php

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
