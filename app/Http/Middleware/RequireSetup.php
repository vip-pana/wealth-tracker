<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a fresh install to the setup page so the app is never reachable without
 * a password, and — the part that matters — blocks the setup page itself once an
 * account exists. Hiding the link would not be enough: /setup creates the
 * account that owns every financial record, so it has to be closed server-side
 * the moment there is something to protect.
 */
class RequireSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        $needsSetup = ! User::query()->exists();
        $isSetupRoute = $request->routeIs('setup.*');

        if ($needsSetup && ! $isSetupRoute) {
            return redirect()->route('setup.create');
        }

        if (! $needsSetup && $isSetupRoute) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
