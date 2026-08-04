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
        // Read the value straight out of .env: this closure runs before Laravel
        // bootstraps Dotenv, so env(), $_SERVER and getenv() are all still empty
        // here — the earlier env() version silently never trusted the proxy, which
        // let http URLs leak into the consent flow and trip mixed-content blocks.
        $redirect = '';
        $envPath = dirname(__DIR__).'/.env';
        if (is_readable($envPath)) {
            $contents = (string) file_get_contents($envPath);
            if (preg_match('/^ENABLE_BANKING_REDIRECT_URL=(.*)$/m', $contents, $m) === 1) {
                $redirect = trim($m[1], " \t\"'");
            }
        }
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
