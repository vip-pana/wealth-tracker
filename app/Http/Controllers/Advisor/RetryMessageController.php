<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Jobs\ContinueChatJob;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use Illuminate\Http\JsonResponse;

class RetryMessageController extends Controller
{
    /**
     * Retry a failed assistant reply in place. The failed message and its
     * preceding user turn are already persisted; this resets the assistant to
     * pending, clears the error, and re-dispatches the same job — so the user
     * can retry with a button instead of resending "riprova" by hand. The
     * polling UI then fills the reply as with a normal send.
     */
    public function __invoke(AdvisorSession $session, AdvisorMessage $message): JsonResponse
    {
        abort_unless($message->session_id === $session->id, 404);
        abort_unless(
            $message->role === AdvisorMessage::ROLE_ASSISTANT && $message->status === AdvisorMessage::STATUS_FAILED,
            422,
            'Solo una risposta non riuscita può essere rigenerata.',
        );

        // The user turn that prompted this reply is the last user message before
        // it. Without one there's nothing to regenerate from.
        $user = $session->messages()
            ->where('role', AdvisorMessage::ROLE_USER)
            ->where('id', '<', $message->id)
            ->orderByDesc('id')
            ->first();

        abort_if($user === null, 422, 'Nessun messaggio da rigenerare.');

        $message->update([
            'status' => AdvisorMessage::STATUS_PENDING,
            'content' => '',
            'error' => null,
            'tool_activity' => null,
            'widgets' => null,
        ]);

        ContinueChatJob::dispatch($user->id, $message->id);

        return response()->json(['status' => 'pending']);
    }
}
