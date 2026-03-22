<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Actions\Analysis\FetchAnalysisData;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalysisRequest;
use Inertia\Inertia;
use Inertia\Response;

class AnalysisController extends Controller
{
    public function __construct(
        private readonly FetchAnalysisData $fetchAnalysisData,
    ) {}

    public function __invoke(AnalysisRequest $request): Response
    {
        return Inertia::render('Analysis', $this->fetchAnalysisData->run($request->categoryId(), $request->dateFrom(), $request->dateTo()));
    }
}
