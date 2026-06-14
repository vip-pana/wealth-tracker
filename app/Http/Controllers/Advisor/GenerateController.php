<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Actions\Advisor\GenerateAdvisorReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class GenerateController extends Controller
{
    public function __construct(
        private readonly GenerateAdvisorReport $generateReport,
    ) {}

    public function __invoke(): JsonResponse
    {
        try {
            $report = $this->generateReport->run();
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        if ($report === null) {
            return response()->json(['error' => 'Consulente AI non configurato.'], 422);
        }

        return response()->json(['report' => $report]);
    }
}
