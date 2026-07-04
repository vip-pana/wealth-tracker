<?php

declare(strict_types=1);

namespace App\Providers;

use App\Advisor\Tools\AdvisorToolActivityReporter;
use App\Advisor\Tools\AdvisorToolFactory;
use App\Contracts\AdvisorProvider;
use App\Http\Clients\EnableBankingClient;
use App\Http\Clients\PrismAdvisorProvider;
use App\Http\Clients\ScalableCliClient;
use Illuminate\Support\Facades\Config;
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
                );
            }

            return new PrismAdvisorProvider(
                Provider::Ollama,
                Config::string('services.advisor.ollama.model', ''),
                Config::integer('services.advisor.ollama.timeout', 120),
                $this->app->make(AdvisorToolFactory::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
