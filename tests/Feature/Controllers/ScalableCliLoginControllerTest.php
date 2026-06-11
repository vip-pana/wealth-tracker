<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Jobs\RunScalableCliLogin;
use App\Services\Scalable\ScalableLoginState;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScalableCliLoginControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_start_dispatches_the_login_job_and_marks_pending(): void
    {
        Queue::fake();

        $this->post('/scalable/cli/login')->assertRedirect();

        Queue::assertPushed(RunScalableCliLogin::class);
        $this->assertSame(ScalableLoginState::PENDING, app(ScalableLoginState::class)->snapshot()['status']);
    }

    public function test_start_does_not_dispatch_a_second_login_while_one_is_in_progress(): void
    {
        Queue::fake();
        app(ScalableLoginState::class)->markPending();

        $this->post('/scalable/cli/login')->assertRedirect();

        Queue::assertNothingPushed();
    }

    public function test_status_returns_the_current_snapshot(): void
    {
        app(ScalableLoginState::class)->markUrlIssued('https://secure.scalable.capital/activate?user_code=ABCD-1234', 'ABCD-1234');

        $this->getJson('/scalable/cli/login/status')
            ->assertOk()
            ->assertJson([
                'status' => ScalableLoginState::URL_ISSUED,
                'user_code' => 'ABCD-1234',
            ]);
    }

    public function test_cancel_clears_an_in_progress_login_back_to_idle(): void
    {
        app(ScalableLoginState::class)->markUrlIssued('https://secure.scalable.capital/activate?user_code=ABCD-1234', 'ABCD-1234');

        $this->post('/scalable/cli/login/cancel')->assertRedirect();

        $this->assertSame('idle', app(ScalableLoginState::class)->snapshot()['status']);
    }
}
