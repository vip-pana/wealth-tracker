<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireSetup;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // RequireSetup runs on every web request: it pushes a fresh install to
        // /setup so the app is never reachable without a password, and closes
        // /setup once an account exists.
        $middleware->web(append: [
            RequireSetup::class,
            HandleInertiaRequests::class,
        ]);

        // RequireSetup has to decide before `auth` does. Otherwise a fresh
        // install sends the visitor to /login, a page they cannot get past
        // because no account exists yet.
        $middleware->priority([
            EncryptCookies::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            RequireSetup::class,
            Authenticate::class,
            ThrottleRequests::class,
            AuthenticateSession::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);

        // Unauthenticated Inertia visits must land on the login page.
        $middleware->redirectGuestsTo(fn () => route('login'));

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
