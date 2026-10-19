<?php

declare(strict_types=1);

namespace App\Providers;

use App\Advisor\AdvisorBudget;
use App\Advisor\Tools\AdvisorToolActivityReporter;
use App\Advisor\Tools\AdvisorToolFactory;
use App\Advisor\Tools\AdvisorWidgetCollector;
use App\Contracts\AdvisorProvider;
use App\Http\Clients\EnableBankingClient;
use App\Http\Clients\PrismAdvisorProvider;
use App\Http\Clients\ScalableCliClient;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Prism\Prism\Enums\Provider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(EnableBankingClient::class, fn (): EnableBankingClient => new EnableBankingClient(
            Config::string('services.enable_banking.application_id', ''),
            Config::string('services.enable_banking.private_key_path', ''),
        ));

        $this->app->singleton(ScalableCliClient::class, fn (): ScalableCliClient => new ScalableCliClient(
            Config::boolean('services.scalable.cli.enabled', false),
            Config::string('services.scalable.cli.binary', 'sc'),
            Config::integer('services.scalable.cli.timeout', 30),
        ));

        // Request-scoped so ContinueChat and the tools (resolved inside the
        // provider) share the same instance: the tools report their live
        // activity to the message ContinueChat armed it with.
        $this->app->singleton(AdvisorToolActivityReporter::class);

        // Same request-scoped sharing for the generative-UI widgets a tool emits
        // (see AdvisorWidgetCollector): the tools append, ContinueChat persists.
        $this->app->singleton(AdvisorWidgetCollector::class);

        // Request-scoped for the same reason: the caller (ContinueChat, the
        // report action, the budget middleware) arms it with the session, and
        // the provider — resolved separately — must see that same instance or
        // the per-session cap would silently never apply.
        $this->app->singleton(AdvisorBudget::class);

        // The advisor is swappable: a local model (Ollama) for development or a
        // cloud one (Anthropic) later, chosen by services.advisor.driver. Both
        // run through Prism, which unifies the tool-calling loop across them;
        // the two providers differ only by their model and endpoint config. We
        // point Prism's own config at the app's existing advisor env vars so the
        // .env stays as it is.
        $this->app->singleton(AdvisorProvider::class, function (): AdvisorProvider {
            $driver = Config::string('services.advisor.driver', 'ollama');

            Config::set('prism.providers.ollama.url', Config::string('services.advisor.ollama.base_url', 'http://host.docker.internal:11434'));

            if ($driver === 'anthropic') {
                return new PrismAdvisorProvider(
                    Provider::Anthropic,
                    Config::string('services.advisor.anthropic.model', ''),
                    Config::integer('services.advisor.anthropic.timeout', 120),
                    $this->app->make(AdvisorToolFactory::class),
                    $this->app->make(AdvisorBudget::class),
                );
            }

            // Regolo speaks the classic /chat/completions API, not OpenAI's newer
            // /responses one that Prism's OpenAI provider now targets. Prism's
            // OpenRouter provider is a plain OpenAI-compatible /chat/completions
            // client, so we reuse it pointed at Regolo's endpoint + key.
            if ($driver === 'regolo') {
                return new PrismAdvisorProvider(
                    Provider::OpenRouter,
                    Config::string('services.advisor.regolo.model', ''),
                    Config::integer('services.advisor.regolo.timeout', 120),
                    $this->app->make(AdvisorToolFactory::class),
                    $this->app->make(AdvisorBudget::class),
                    [
                        'temperature' => Config::float('services.advisor.regolo.temperature', 0.2),
                    ],
                    [
                        'url' => Config::string('services.advisor.regolo.base_url', 'https://api.regolo.ai/v1'),
                        'api_key' => Config::string('services.advisor.regolo.api_key', ''),
                    ],
                );
            }

            return new PrismAdvisorProvider(
                Provider::Ollama,
                Config::string('services.advisor.ollama.model', ''),
                Config::integer('services.advisor.ollama.timeout', 120),
                $this->app->make(AdvisorToolFactory::class),
                $this->app->make(AdvisorBudget::class),
                [
                    'temperature' => Config::float('services.advisor.ollama.temperature', 0.3),
                    'keep_alive' => Config::string('services.advisor.ollama.keep_alive', '30m'),
                    'num_ctx' => Config::integer('services.advisor.ollama.num_ctx', 8192),
                ],
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Generate https URLs whenever APP_URL says the app is served over
        // https. Trusting X-Forwarded-* (see bootstrap/app.php) covers this
        // only if the proxy in front actually sends those headers, and
        // `tailscale serve` does not: without this, redirects came out as
        // http:// and a `secure` session cookie was never sent back, so the
        // login silently looped.
        //
        // APP_URL is the right source: it already has to match the address the
        // app is opened at, or absolute URLs and the bank-consent redirect
        // break anyway.
        if (str_starts_with(Config::string('app.url', ''), 'https://')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiters();
    }

    /**
     * Named rate limiters for the routes that cost something real: money (the
     * advisor's cloud inference), a third party's quota (the bank, the broker,
     * the price feed), or the single SQLite writer (a synchronous VACUUM INTO).
     *
     * Everything is keyed by user id — the app is single-user, so that is one
     * bucket — falling back to the client IP for the handful of requests that
     * can arrive unauthenticated. Limits are deliberately far above normal human
     * use and far below what a loop can do in a minute: they exist to stop a
     * runaway script or a stolen session, not to pace the owner.
     */
    private function configureRateLimiters(): void
    {
        $by = static function (Request $request): string {
            $user = $request->user();

            return $user instanceof User ? 'user:'.$user->id : 'ip:'.$request->ip();
        };

        // A backup is a synchronous VACUUM INTO of the whole database, plus an
        // encrypt and an upload. Hammering it is the sharpest self-DoS the app
        // has; nobody needs more than a handful a day by hand.
        RateLimiter::for('backup', fn (Request $request) => Limit::perHour(3)->by($by($request)));

        // Each import parses an uploaded file and writes a batch of rows. The
        // 2 MB upload cap blunts a single call; this blunts a thousand of them.
        RateLimiter::for('import', fn (Request $request) => Limit::perHour(10)->by($by($request)));

        // Starting a bank consent and refreshing prices both spend a third
        // party's quota, and a bank that rate-limits us answers 429 for
        // everything else too — including the scheduled sync.
        RateLimiter::for('banking', fn (Request $request) => Limit::perHour(10)->by($by($request)));
        RateLimiter::for('prices', fn (Request $request) => Limit::perHour(12)->by($by($request)));

        // A broker login spawns a CLI process and opens a device-code session.
        // Two concurrent ones confuse the CLI's single session directory.
        RateLimiter::for('scalable-login', fn (Request $request) => Limit::perHour(5)->by($by($request)));

        // The advisor endpoints that reach the model. These are the ones that
        // can cost money on a cloud driver, so they are the tightest. The token
        // budget (see AdvisorBudget) caps the spend; this caps the rate.
        RateLimiter::for('advisor-generate', fn (Request $request) => Limit::perHour(30)->by($by($request)));

        // The polling endpoint. Conversation.tsx polls it every 2.5s while a
        // reply generates — ~24/min per open tab — so this has to sit well above
        // that or a second tab starts failing the user's own conversation.
        RateLimiter::for('advisor-poll', fn (Request $request) => Limit::perMinute(180)->by($by($request)));

        // The remaining advisor routes: pages, renames, deletions, and accepting
        // a proposal card. No model call, so these are plain write limits.
        RateLimiter::for('advisor', fn (Request $request) => Limit::perMinute(60)->by($by($request)));
    }
}
