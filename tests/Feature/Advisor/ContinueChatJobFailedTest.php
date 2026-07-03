<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Jobs\ContinueChatJob;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContinueChatJobFailedTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_marks_a_still_pending_reply_as_failed(): void
    {
        $session = AdvisorSession::create(['kind' => 'chat', 'status' => 'done']);
        $user = $session->messages()->create([
            'role' => AdvisorMessage::ROLE_USER,
            'content' => 'domanda',
            'status' => AdvisorMessage::STATUS_DONE,
        ]);
        $assistant = $session->messages()->create([
            'role' => AdvisorMessage::ROLE_ASSISTANT,
            'content' => '',
            'status' => AdvisorMessage::STATUS_PENDING,
        ]);

        (new ContinueChatJob($user->id, $assistant->id))->failed(new \RuntimeException('timeout'));

        $assistant->refresh();
        $this->assertSame(AdvisorMessage::STATUS_FAILED, $assistant->status);
        $this->assertNotNull($assistant->error);
    }

    public function test_failed_leaves_an_already_resolved_reply_untouched(): void
    {
        $session = AdvisorSession::create(['kind' => 'chat', 'status' => 'done']);
        $user = $session->messages()->create([
            'role' => AdvisorMessage::ROLE_USER,
            'content' => 'domanda',
            'status' => AdvisorMessage::STATUS_DONE,
        ]);
        $assistant = $session->messages()->create([
            'role' => AdvisorMessage::ROLE_ASSISTANT,
            'content' => 'Risposta reale.',
            'status' => AdvisorMessage::STATUS_DONE,
        ]);

        (new ContinueChatJob($user->id, $assistant->id))->failed(new \RuntimeException('timeout'));

        $this->assertSame(AdvisorMessage::STATUS_DONE, $assistant->fresh()->status);
    }
}
