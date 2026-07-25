<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Http\Clients\ScalableCliClient;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class ScalableKeepAliveTest extends TestCase
{
    public function test_does_nothing_when_the_scalable_cli_is_disabled(): void
    {
        Config::set('services.scalable.cli.enabled', false);

        $cli = Mockery::mock(ScalableCliClient::class);
        $cli->shouldNotReceive('isLoggedIn');
        $this->app->instance(ScalableCliClient::class, $cli);

        $this->artisan('scalable:keep-alive')->assertSuccessful();
    }

    public function test_pings_the_cli_when_enabled(): void
    {
        Config::set('services.scalable.cli.enabled', true);

        $cli = Mockery::mock(ScalableCliClient::class);
        $cli->shouldReceive('isLoggedIn')->once()->andReturnTrue();
        $this->app->instance(ScalableCliClient::class, $cli);

        $this->artisan('scalable:keep-alive')->assertSuccessful();
    }
}
