<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvisorNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function doneSession(): AdvisorSession
    {
        $session = AdvisorSession::create(['kind' => 'chat', 'status' => AdvisorSession::STATUS_DONE]);
        $session->messages()->create([
            'role' => AdvisorMessage::ROLE_ASSISTANT,
            'content' => 'ready',
            'status' => AdvisorMessage::STATUS_DONE,
        ]);

        return $session;
    }

    public function test_opening_a_session_marks_its_notification_read(): void
    {
        $session = $this->doneSession();
        $notification = Notification::create([
            'type' => Notification::TYPE_ADVISOR_CHAT_READY,
            'level' => Notification::LEVEL_SUCCESS,
            'title' => 'Risposta pronta',
            'action_url' => '/advisor/'.$session->id,
        ]);

        $this->get('/advisor/'.$session->id)->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_notification_for_another_session_stays_unread(): void
    {
        $opened = $this->doneSession();
        $other = $this->doneSession();
        $notification = Notification::create([
            'type' => Notification::TYPE_ADVISOR_CHAT_READY,
            'level' => Notification::LEVEL_SUCCESS,
            'title' => 'Risposta pronta',
            'action_url' => '/advisor/'.$other->id,
        ]);

        $this->get('/advisor/'.$opened->id)->assertOk();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_a_generating_session_does_not_mark_its_notification_read(): void
    {
        $session = AdvisorSession::create(['kind' => 'chat', 'status' => AdvisorSession::STATUS_PENDING]);
        $notification = Notification::create([
            'type' => Notification::TYPE_ADVISOR_CHAT_READY,
            'level' => Notification::LEVEL_SUCCESS,
            'title' => 'In corso',
            'action_url' => '/advisor/'.$session->id,
        ]);

        $this->get('/advisor/'.$session->id)->assertOk();

        $this->assertNull($notification->fresh()->read_at);
    }
}
