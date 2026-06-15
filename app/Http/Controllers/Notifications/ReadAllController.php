<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class ReadAllController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        Notification::query()->unread()->update(['read_at' => Carbon::now()]);

        return redirect()->back();
    }
}
