<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Only when a TLS tunnel is in use (the bank-consent redirect points at an
        // https, non-localhost host) do we trust the proxy's X-Forwarded-* headers,
        // so Laravel generates https URLs instead of mixed-content http ones. In
        // normal local use the redirect is empty/localhost and no proxy is trusted.
        // Uses env() because the config service isn't bootstrapped at this point;
        // the tunnel is a dev-time flow where config is never cached anyway.
        $redirect = (string) env('ENABLE_BANKING_REDIRECT_URL', '');
        $host = parse_url($redirect, PHP_URL_HOST);
        $behindTunnel = str_starts_with($redirect, 'https://')
            && is_string($host)
            && ! in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true);

        if ($behindTunnel) {
            $middleware->trustProxies(at: '*');
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
