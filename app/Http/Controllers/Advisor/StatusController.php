<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    /**
     * Poll a session's generation status while its opening report is being
     * produced in the background. Returns the status and, once done, the
     * session messages so the UI can render the report without a full reload.
     */
    public function __invoke(AdvisorSession $session): JsonResponse
    {
        return response()->json([
            'status' => $session->status,
            'error' => $session->error,
            'messages' => $session->messages()
                ->get()
                ->map(fn (AdvisorMessage $m): array => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'content' => $m->content,
                    'status' => $m->status,
                    'error' => $m->error,
                    'tool_activity' => $m->tool_activity,
                    'created_at' => $m->created_at?->toISOString(),
                ])
                ->all(),
        ]);
    }
}
