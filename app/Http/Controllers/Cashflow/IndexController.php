<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashflow;

use App\Actions\Cashflow\FetchCashflowData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashflow\CashflowRequest;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __construct(
        private readonly FetchCashflowData $fetchCashflowData,
    ) {}

    public function __invoke(CashflowRequest $request): Response
    {
        return Inertia::render('Cashflow', $this->fetchCashflowData->run($request->month()));
    }
}
