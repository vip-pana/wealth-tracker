<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Advisor\ContinueChat;
use App\Actions\Notifications\PushNotification;
use App\Models\AdvisorMessage;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generates a chat reply in the background, so the web request returns
 * immediately and the user can navigate away while a local model takes its
 * time. The user turn and an empty `pending` assistant turn are already
 * persisted by the controller; this fills the assistant turn in place. One
 * attempt: a stale half-reply isn't worth retrying.
 */
class ContinueChatJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        private readonly int $userMessageId,
        private readonly int $assistantMessageId,
    ) {}

    public function handle(ContinueChat $continueChat, PushNotification $notify): void
    {
        $user = AdvisorMessage::find($this->userMessageId);
        $assistant = AdvisorMessage::find($this->assistantMessageId);

        if ($user === null || $assistant === null) {
            return;
        }

        $session = $assistant->session;
        $continueChat->complete($session, $user, $assistant);

        // Notify so the user knows the reply is ready if they navigated away
        // while it generated. The sidebar's unread dot handles the in-page case;
        // this covers being on another page or with the tab closed.
        $assistant->refresh();
        if ($assistant->status === AdvisorMessage::STATUS_FAILED) {
            $notify->run(
                type: Notification::TYPE_ADVISOR_REPORT_FAILED,
                level: Notification::LEVEL_WARNING,
                title: 'Risposta non riuscita',
                body: 'Il consulente non ha risposto. Verifica che il modello locale sia in esecuzione.',
                actionUrl: '/advisor/'.$session->id,
            );

            return;
        }

        $notify->run(
            type: Notification::TYPE_ADVISOR_CHAT_READY,
            level: Notification::LEVEL_SUCCESS,
            title: 'Risposta pronta',
            body: 'Il consulente AI ha risposto nella tua conversazione.',
            actionUrl: '/advisor/'.$session->id,
        );
    }
}
