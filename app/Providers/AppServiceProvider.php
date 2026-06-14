<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AdvisorProvider;
use App\Http\Clients\EnableBankingClient;
use App\Http\Clients\OllamaAdvisorProvider;
use App\Http\Clients\ScalableCliClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
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

        // The advisor is swappable: a local model (Ollama) for development and
        // a cloud one (Claude) later, chosen by services.advisor.driver. Only
        // the Ollama provider exists today; resolve it for any driver for now.
        $this->app->singleton(AdvisorProvider::class, fn (): AdvisorProvider => new OllamaAdvisorProvider(
            Config::string('services.advisor.ollama.base_url', 'http://host.docker.internal:11434'),
            Config::string('services.advisor.ollama.model', ''),
            Config::integer('services.advisor.ollama.timeout', 120),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
