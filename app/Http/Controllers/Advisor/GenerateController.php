<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Contracts\AdvisorProvider;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateAdvisorReportJob;
use App\Models\AdvisorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class GenerateController extends Controller
{
    public function __construct(
        private readonly AdvisorProvider $provider,
    ) {}

    public function __invoke(): JsonResponse
    {
        if (! $this->provider->isConfigured()) {
            return response()->json(['error' => 'Consulente AI non configurato.'], 422);
        }

        // The local model serves one request at a time, so don't open a second
        // generation while one is still running — point the user at it instead.
        $pending = AdvisorSession::query()
            ->where('kind', AdvisorSession::KIND_REPORT)
            ->where('status', AdvisorSession::STATUS_PENDING)
            ->latest('id')
            ->first();

        if ($pending !== null) {
            return response()->json(['session_id' => $pending->id, 'status' => AdvisorSession::STATUS_PENDING]);
        }

        // Each generation opens a NEW report session; old ones stay in history.
        // The opening analysis is produced in the background so the request
        // returns immediately and survives navigating away or closing the tab.
        $session = AdvisorSession::create([
            'kind' => AdvisorSession::KIND_REPORT,
            'title' => 'Analisi '.Carbon::now()->translatedFormat('j M Y'),
            'status' => AdvisorSession::STATUS_PENDING,
        ]);

        GenerateAdvisorReportJob::dispatch($session->id);

        return response()->json(['session_id' => $session->id, 'status' => AdvisorSession::STATUS_PENDING]);
    }
}
