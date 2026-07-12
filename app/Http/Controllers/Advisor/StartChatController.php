<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Contracts\AdvisorProvider;
use App\Http\Controllers\Controller;
use App\Jobs\ContinueChatJob;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StartChatController extends Controller
{
    public function __construct(
        private readonly AdvisorProvider $provider,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->provider->isConfigured()) {
            return response()->json(['error' => 'Consulente AI non configurato.'], 422);
        }

        /** @var array{message: string} $data */
        $data = $request->validate([
            'message' => 'required|string|max:4000',
        ]);

        // Persist the session, the user turn and an empty pending assistant
        // turn, then generate the reply in the background (same as follow-up
        // messages) so the request returns immediately and the app stays
        // navigable while the local model works. The title previews the question.
        $session = AdvisorSession::create([
            'kind' => $this->kindFor($data['message']),
            'title' => Str::limit($data['message'], 60),
            'status' => AdvisorSession::STATUS_DONE,
        ]);

        $user = AdvisorMessage::create([
            'session_id' => $session->id,
            'role' => AdvisorMessage::ROLE_USER,
            'content' => $data['message'],
            'status' => AdvisorMessage::STATUS_DONE,
        ]);

        $assistant = AdvisorMessage::create([
            'session_id' => $session->id,
            'role' => AdvisorMessage::ROLE_ASSISTANT,
            'content' => '',
            'status' => AdvisorMessage::STATUS_PENDING,
        ]);

        ContinueChatJob::dispatch($user->id, $assistant->id);

        return response()->json(['session_id' => $session->id]);
    }

    /**
     * Tag the new session as a goal/profile interview when the opening message
     * states that intent (the "Ridefinisci con l'AI" button, the profile
     * starter), otherwise a plain chat. Only interview sessions surface the
     * "generate proposal" button.
     */
    private function kindFor(string $message): string
    {
        return AdvisorSession::interviewIntentKind($message) ?? AdvisorSession::KIND_CHAT;
    }
}
