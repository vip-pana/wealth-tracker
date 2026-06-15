<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Actions\Advisor\ContinueChat;
use App\Contracts\AdvisorProvider;
use App\Http\Controllers\Controller;
use App\Models\AdvisorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StartChatController extends Controller
{
    public function __construct(
        private readonly AdvisorProvider $provider,
        private readonly ContinueChat $continueChat,
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

        // A free chat starts already "done" (no background report to await);
        // the title is a short preview of the opening question.
        $session = AdvisorSession::create([
            'kind' => AdvisorSession::KIND_CHAT,
            'title' => Str::limit($data['message'], 60),
            'status' => AdvisorSession::STATUS_DONE,
        ]);

        $reply = $this->continueChat->run($session, $data['message']);

        if ($reply === null) {
            $session->delete();

            return response()->json(['error' => 'Consulente AI non configurato.'], 422);
        }

        return response()->json([
            'session_id' => $session->id,
            'message' => [
                'id' => $reply->id,
                'role' => $reply->role,
                'content' => $reply->content,
                'created_at' => $reply->created_at?->toISOString(),
            ],
        ]);
    }
}
