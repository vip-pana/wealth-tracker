<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Clients\EnableBankingClient;
use App\Http\Clients\ScalableCliClient;
use App\Http\Clients\ScalableUnofficialClient;
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

        $this->app->singleton(ScalableUnofficialClient::class, fn (): ScalableUnofficialClient => new ScalableUnofficialClient(
            Config::string('services.scalable.balance_url', ''),
            Config::string('services.scalable.token', ''),
        ));

        $this->app->singleton(ScalableCliClient::class, fn (): ScalableCliClient => new ScalableCliClient(
            Config::boolean('services.scalable.cli.enabled', false),
            Config::string('services.scalable.cli.binary', 'sc'),
            Config::integer('services.scalable.cli.timeout', 30),
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
