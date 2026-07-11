<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Jobs\ContinueChatJob;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RetryMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * @return array{0: AdvisorSession, 1: AdvisorMessage, 2: AdvisorMessage}
     */
    private function failedExchange(): array
    {
        $session = AdvisorSession::create(['kind' => 'chat', 'title' => 't', 'status' => 'done']);
        $user = AdvisorMessage::create(['session_id' => $session->id, 'role' => 'user', 'content' => 'ciao', 'status' => 'done']);
        $assistant = AdvisorMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => '',
            'status' => 'failed',
            'error' => 'Il consulente non ha risposto.',
        ]);

        return [$session, $user, $assistant];
    }

    public function test_retry_resets_the_message_to_pending_and_dispatches_the_job(): void
    {
        Queue::fake();
        [$session, , $assistant] = $this->failedExchange();

        $this->post("/advisor/{$session->id}/message/{$assistant->id}/retry")
            ->assertOk()
            ->assertJson(['status' => 'pending']);

        $assistant->refresh();
        $this->assertSame(AdvisorMessage::STATUS_PENDING, $assistant->status);
        $this->assertNull($assistant->error);

        Queue::assertPushed(ContinueChatJob::class);
    }

    public function test_retry_rejects_a_message_that_did_not_fail(): void
    {
        Queue::fake();
        $session = AdvisorSession::create(['kind' => 'chat', 'title' => 't', 'status' => 'done']);
        AdvisorMessage::create(['session_id' => $session->id, 'role' => 'user', 'content' => 'ciao', 'status' => 'done']);
        $done = AdvisorMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => 'ok', 'status' => 'done']);

        $this->post("/advisor/{$session->id}/message/{$done->id}/retry")->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_retry_rejects_a_message_from_another_session(): void
    {
        Queue::fake();
        [, , $assistant] = $this->failedExchange();
        $other = AdvisorSession::create(['kind' => 'chat', 'title' => 'other', 'status' => 'done']);

        $this->post("/advisor/{$other->id}/message/{$assistant->id}/retry")->assertStatus(404);

        Queue::assertNothingPushed();
    }
}
