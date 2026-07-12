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
 * Generates a PROPOSAL card in the background, triggered by the user clicking
 * the "generate the proposal" button (not a chat message). The controller has
 * already persisted an empty `pending` assistant turn; this fills it in place
 * by asking the model to call the propose_* tool with the interview so far.
 * `$kind` is 'profile' or 'goal'. Mirrors ContinueChatJob's lifecycle.
 */
class ProposeNowJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        private readonly int $assistantMessageId,
        private readonly string $kind,
    ) {}

    public function handle(ContinueChat $continueChat, PushNotification $notify): void
    {
        $assistant = AdvisorMessage::find($this->assistantMessageId);

        if ($assistant === null) {
            return;
        }

        $session = $assistant->session;
        $continueChat->proposeNow($session, $assistant, $this->kind);

        $assistant->refresh();
        if ($assistant->status === AdvisorMessage::STATUS_FAILED) {
            $notify->run(
                type: Notification::TYPE_ADVISOR_REPORT_FAILED,
                level: Notification::LEVEL_WARNING,
                title: 'Proposta non riuscita',
                body: 'Il consulente non ha generato la proposta. Riprova.',
                actionUrl: '/advisor/'.$session->id,
            );

            return;
        }

        $notify->run(
            type: Notification::TYPE_ADVISOR_CHAT_READY,
            level: Notification::LEVEL_SUCCESS,
            title: 'Proposta pronta',
            body: 'Il consulente AI ha preparato una proposta nella tua conversazione.',
            actionUrl: '/advisor/'.$session->id,
        );
    }

    public function failed(?\Throwable $e): void
    {
        $assistant = AdvisorMessage::find($this->assistantMessageId);

        if ($assistant === null || $assistant->status !== AdvisorMessage::STATUS_PENDING) {
            return;
        }

        $assistant->update([
            'status' => AdvisorMessage::STATUS_FAILED,
            'error' => 'Il consulente non ha risposto. Riprova.',
        ]);
    }
}
