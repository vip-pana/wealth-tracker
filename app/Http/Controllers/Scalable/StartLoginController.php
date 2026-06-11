<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scalable;

use App\Http\Controllers\Controller;
use App\Jobs\RunScalableCliLogin;
use App\Services\Scalable\ScalableLoginState;
use Illuminate\Http\RedirectResponse;

class StartLoginController extends Controller
{
    public function __construct(
        private readonly ScalableLoginState $state,
    ) {}

    public function __invoke(): RedirectResponse
    {
        // A login is already running: don't spawn a second `sc login` with a
        // different code. The UI keeps polling the existing one.
        if ($this->state->isInProgress()) {
            return redirect()->back()->with('info', 'Login Scalable già in corso.');
        }

        $this->state->markPending();
        RunScalableCliLogin::dispatch();

        return redirect()->back();
    }
}
