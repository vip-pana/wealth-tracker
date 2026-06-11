<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scalable;

use App\Http\Controllers\Controller;
use App\Services\Scalable\ScalableLoginState;
use Illuminate\Http\RedirectResponse;

class CancelLoginController extends Controller
{
    public function __construct(
        private readonly ScalableLoginState $state,
    ) {}

    public function __invoke(): RedirectResponse
    {
        // Drop the in-flight login state so the UI escapes "In attesa di
        // conferma…" at once. The orphaned `sc login` worker self-expires on
        // its own timeout; we just stop tracking it.
        $this->state->clear();

        return redirect()->back();
    }
}
