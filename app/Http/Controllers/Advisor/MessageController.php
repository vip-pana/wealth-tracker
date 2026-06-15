<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Actions\Advisor\ContinueChat;
use App\Contracts\AdvisorProvider;
use App\Http\Controllers\Controller;
use App\Models\AdvisorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        private readonly AdvisorProvider $provider,
        private readonly ContinueChat $continueChat,
    ) {}

    public function __invoke(Request $request, AdvisorSession $session): JsonResponse
    {
        if (! $this->provider->isConfigured()) {
            return response()->json(['error' => 'Consulente AI non configurato.'], 422);
        }

        /** @var array{message: string} $data */
        $data = $request->validate([
            'message' => 'required|string|max:4000',
        ]);

        // Synchronous: the user is actively waiting on the reply (unlike the
        // background report). The local model serves one request at a time.
        $reply = $this->continueChat->run($session, $data['message']);

        if ($reply === null) {
            return response()->json(['error' => 'Consulente AI non configurato.'], 422);
        }

        return response()->json([
            'message' => [
                'id' => $reply->id,
                'role' => $reply->role,
                'content' => $reply->content,
                'created_at' => $reply->created_at?->toISOString(),
            ],
        ]);
    }
}
