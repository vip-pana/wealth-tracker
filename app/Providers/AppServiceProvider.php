<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Clients\EnableBankingClient;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
