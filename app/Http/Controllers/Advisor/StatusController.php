<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\AdvisorReport;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    /**
     * The current report's status for the frontend poll: idle when none exists,
     * otherwise pending / done (with content + when) / failed (with error).
     */
    public function __invoke(): JsonResponse
    {
        $report = AdvisorReport::current();

        if ($report === null) {
            return response()->json(['status' => 'idle']);
        }

        return response()->json([
            'status' => $report->status,
            'content' => $report->content,
            'error' => $report->error,
            'generated_at' => $report->updated_at?->toISOString(),
        ]);
    }
}
