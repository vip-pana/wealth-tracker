<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scalable;

use App\Http\Clients\ScalableCliClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class LogoutController extends Controller
{
    public function __construct(
        private readonly ScalableCliClient $cli,
    ) {}

    public function __invoke(): RedirectResponse
    {
        if ($this->cli->logout()) {
            return redirect()->back()->with('success', 'Sessione Scalable scollegata.');
        }

        return redirect()->back()->with('error', 'Impossibile scollegare la sessione Scalable.');
    }
}
