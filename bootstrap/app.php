<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAdvisorBudget;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireSetup;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
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

        // Refuses an advisor request whose token budget is already spent, so the
        // user gets a readable 422 now instead of a pending bubble that fails
        // minutes later in the queue. See EnsureAdvisorBudget.
        $middleware->alias(['advisor.budget' => EnsureAdvisorBudget::class]);

        // Unauthenticated Inertia visits must land on the login page.
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Only when something terminates TLS in front of us do we trust the
        // proxy's X-Forwarded-* headers, so Laravel generates https URLs instead
        // of mixed-content http ones. In normal local use nothing does, and no
        // proxy is trusted.
        //
        // Two things can put us behind TLS: the bank-consent tunnel, or serving
        // the whole app over https (a reverse proxy, or `tailscale serve`).
        // Either is enough, so both are checked.
        //
        // This closure runs before Laravel bootstraps Dotenv, so env() is still
        // empty — the original version used it and silently never trusted the
        // proxy. Reading .env directly fixed that locally but not in Docker,
        // where there is no .env file at all and the values arrive as real
        // environment variables (`env_file` in compose). Hence both sources:
        // getenv() first, the file as the fallback.
        $fromEnvFile = static function (string $key): string {
            $envPath = dirname(__DIR__).'/.env';
            if (! is_readable($envPath)) {
                return '';
            }
            $contents = (string) file_get_contents($envPath);
            if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $m) !== 1) {
                return '';
            }

            return trim($m[1], " \t\"'");
        };

        $setting = static function (string $key) use ($fromEnvFile): string {
            $value = getenv($key);

            return is_string($value) && $value !== '' ? $value : $fromEnvFile($key);
        };

        $isRemoteHttps = static function (string $url): bool {
            if (! str_starts_with($url, 'https://')) {
                return false;
            }
            $host = parse_url($url, PHP_URL_HOST);

            return is_string($host)
                && ! in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true);
        };

        if ($isRemoteHttps($setting('ENABLE_BANKING_REDIRECT_URL'))
            || $isRemoteHttps($setting('APP_URL'))) {
            $middleware->trustProxies(at: '*');
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A throttled request otherwise renders Laravel's bare 429 page, which
        // tells the user nothing and looks like the app broke. Inertia visits
        // bounce back with a flash error (shown as a toast); the advisor's plain
        // axios calls get JSON they already know how to display. Anything else
        // — curl, a direct GET — falls through to the default page.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            $message = 'Troppe richieste in poco tempo. Riprova tra qualche minuto.';

            if ($request->hasHeader('X-Inertia')) {
                // 303 rather than 302: Inertia requires it after a PATCH or
                // DELETE, and it is equally correct after a POST.
                return back(303)->with('error', $message);
            }

            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 429);
            }

            return null;
        });
    })->create();
