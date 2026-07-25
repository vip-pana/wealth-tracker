<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Contracts\AdvisorProvider;
use App\Http\Controllers\Controller;
use App\Jobs\ProposeNowJob;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use Illuminate\Http\JsonResponse;

class ProposeController extends Controller
{
    public function __construct(
        private readonly AdvisorProvider $provider,
    ) {}

    /**
     * Generate a proposal card on demand: the user clicked the "generate the
     * proposal" button the advisor offered. Unlike a chat message this adds NO
     * user turn — only an empty pending assistant turn, filled in the background
     * by ProposeNowJob. The click is the consent, so the job opens the gate.
     */
    public function __invoke(AdvisorSession $session, string $kind): JsonResponse
    {
        abort_unless($this->provider->isConfigured(), 422, 'Consulente AI non configurato.');
        abort_unless(in_array($kind, ['profile', 'goal'], true), 404);

        $assistant = AdvisorMessage::create([
            'session_id' => $session->id,
            'role' => AdvisorMessage::ROLE_ASSISTANT,
            'content' => '',
            'status' => AdvisorMessage::STATUS_PENDING,
        ]);

        ProposeNowJob::dispatch($assistant->id, $kind);

        return response()->json(['assistant' => $this->serialize($assistant)]);
    }

    /**
     * @return array{id: int, role: string, content: string, status: string, error: string|null, tool_activity: string|null, widgets: list<array{type: string, data: array<string, mixed>}>|null, created_at: string|null}
     */
    private function serialize(AdvisorMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'status' => $message->status,
            'error' => $message->error,
            'tool_activity' => $message->tool_activity,
            'widgets' => $message->widgets,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }
}
