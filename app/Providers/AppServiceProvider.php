<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Clients\GoCardlessClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GoCardlessClient::class, fn (): GoCardlessClient => new GoCardlessClient(
            Config::string('services.gocardless.secret_id', ''),
            Config::string('services.gocardless.secret_key', ''),
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
