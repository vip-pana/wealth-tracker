<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Actions\Advisor\ComputePositionReturns;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentsController extends Controller
{
    public function __construct(
        private readonly ComputePositionReturns $computePositionReturns,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Investments', [
            'returns' => $this->computePositionReturns->run(),
        ]);
    }
}
