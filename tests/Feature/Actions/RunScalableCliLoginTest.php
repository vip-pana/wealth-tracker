<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Scalable\RunScalableCliLogin;
use App\Models\ScalableConnection;
use App\Services\Scalable\ScalableLoginState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RunScalableCliLoginTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN_OUTPUT = "Open this URL:\nhttps://secure.scalable.capital/activate?user_code=ABCD-1234\nVerify the code ABCD-1234 in your browser.\nWaiting for browser confirmation...\nLogged in via device code.";

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.scalable.cli.enabled' => true]);
    }

    private function whoamiOk(): string
    {
        return (string) json_encode(['ok' => true, 'data' => ['result' => ['personOverview' => ['id' => 'x']]]]);
    }

    public function test_captures_the_url_and_completes_on_a_live_session(): void
    {
        Process::fake([
            '*login*' => Process::result(self::LOGIN_OUTPUT),
            '*whoami*' => Process::result($this->whoamiOk()),
        ]);

        $state = app(ScalableLoginState::class);
        $state->markPending();
        app(RunScalableCliLogin::class)->run();

        $snapshot = $state->snapshot();
        $this->assertSame(ScalableLoginState::COMPLETE, $snapshot['status']);
        $this->assertSame('https://secure.scalable.capital/activate?user_code=ABCD-1234', $snapshot['url']);
        $this->assertSame('ABCD-1234', $snapshot['user_code']);
        $this->assertSame(ScalableConnection::SYNC_OK, ScalableConnection::current()->last_sync_status);
    }

    public function test_fails_when_whoami_does_not_confirm_the_session(): void
    {
        Process::fake([
            '*login*' => Process::result(self::LOGIN_OUTPUT),
            '*whoami*' => Process::result((string) json_encode(['ok' => false, 'error' => ['code' => 'no_session']])),
        ]);

        $state = app(ScalableLoginState::class);
        $state->markPending();
        app(RunScalableCliLogin::class)->run();

        $this->assertSame(ScalableLoginState::FAILED, $state->snapshot()['status']);
        $this->assertSame(ScalableConnection::SYNC_FAILED, ScalableConnection::current()->last_sync_status);
    }

    public function test_fails_when_login_exits_non_zero(): void
    {
        Process::fake([
            '*login*' => Process::result('', 'login error', 1),
            '*whoami*' => Process::result($this->whoamiOk()),
        ]);

        $state = app(ScalableLoginState::class);
        $state->markPending();
        app(RunScalableCliLogin::class)->run();

        $this->assertSame(ScalableLoginState::FAILED, $state->snapshot()['status']);
    }

    public function test_fails_without_issuing_a_url_when_output_has_no_activation_link(): void
    {
        // CLI ran but printed no activation URL (e.g. a wording drift): no URL is
        // published and, with whoami still reporting no session, the login fails.
        Process::fake([
            '*login*' => Process::result('Unexpected output with no link'),
            '*whoami*' => Process::result((string) json_encode(['ok' => false, 'error' => ['code' => 'no_session']])),
        ]);

        $state = app(ScalableLoginState::class);
        $state->markPending();
        app(RunScalableCliLogin::class)->run();

        $snapshot = $state->snapshot();
        $this->assertSame(ScalableLoginState::FAILED, $snapshot['status']);
        $this->assertNull($snapshot['url']);
    }
}
