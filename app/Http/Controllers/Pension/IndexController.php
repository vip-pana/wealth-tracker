<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pension;

use App\Actions\Pension\FetchPensionData;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __construct(
        private readonly FetchPensionData $fetchPensionData,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Pension', $this->fetchPensionData->run());
    }
}
