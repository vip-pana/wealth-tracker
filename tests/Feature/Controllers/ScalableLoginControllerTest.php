<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScalableLoginControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['services.scalable.balance_url' => 'http://scalable.test', 'services.scalable.token' => 'tok']);
    }

    public function test_flashes_success_when_the_proxy_logs_in(): void
    {
        Http::fake(['scalable.test/auth/login' => Http::response(['message' => 'Login successful.'])]);

        $this->post('/scalable/login')
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('error');
    }

    public function test_flashes_error_when_the_proxy_rejects(): void
    {
        Http::fake(['scalable.test/auth/login' => Http::response('', 500)]);

        $this->post('/scalable/login')
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_flashes_error_when_unconfigured(): void
    {
        config(['services.scalable.balance_url' => '']);

        $this->post('/scalable/login')
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
