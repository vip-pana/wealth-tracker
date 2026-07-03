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

class MessageController extends Controller
{
    public function __construct(
        private readonly AdvisorProvider $provider,
    ) {}

    public function __invoke(Request $request, AdvisorSession $session): JsonResponse
    {
        abort_unless($this->provider->isConfigured(), 422, 'Consulente AI non configurato.');

        /** @var array{message: string} $data */
        $data = $request->validate([
            'message' => 'required|string|max:4000',
        ]);

        // Persist the user turn and an empty pending assistant turn now, then
        // generate the reply in the background. The request returns immediately
        // so the app stays navigable while the local model works (a streamed
        // response froze the whole server); the UI shows the two messages and
        // polls the assistant one until it flips to done.
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

        return response()->json([
            'user' => $this->serialize($user),
            'assistant' => $this->serialize($assistant),
        ]);
    }

    /**
     * @return array{id: int, role: string, content: string, status: string, error: string|null, created_at: string|null}
     */
    private function serialize(AdvisorMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'status' => $message->status,
            'error' => $message->error,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }
}
