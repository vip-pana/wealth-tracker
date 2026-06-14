<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Contracts\AdvisorProvider;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateAdvisorReportJob;
use App\Models\AdvisorReport;
use Illuminate\Http\JsonResponse;

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

        // Don't queue a second generation while one is already running — the
        // local model serves one request at a time, so a concurrent job would
        // just time out. Report the in-progress one instead.
        $current = AdvisorReport::current();
        if ($current !== null && $current->status === AdvisorReport::STATUS_PENDING) {
            return response()->json(['status' => AdvisorReport::STATUS_PENDING]);
        }

        // Replace any previous report (single-row for now) and queue the work,
        // so the request returns immediately and generation survives the user
        // navigating away or closing the tab.
        AdvisorReport::query()->delete();
        $report = AdvisorReport::create(['status' => AdvisorReport::STATUS_PENDING]);

        GenerateAdvisorReportJob::dispatch($report->id);

        return response()->json(['status' => AdvisorReport::STATUS_PENDING]);
    }
}
