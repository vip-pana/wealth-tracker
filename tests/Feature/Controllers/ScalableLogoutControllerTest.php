<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ScalableLogoutControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['services.scalable.cli.enabled' => true]);
    }

    public function test_flashes_success_when_the_cli_logs_out(): void
    {
        Process::fake(['*logout*' => Process::result((string) json_encode(['ok' => true, 'data' => ['logged_out' => true]]))]);

        $this->post('/scalable/cli/logout')
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_flashes_error_when_logout_fails(): void
    {
        Process::fake(['*logout*' => Process::result('', '', 1)]);

        $this->post('/scalable/cli/logout')
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
