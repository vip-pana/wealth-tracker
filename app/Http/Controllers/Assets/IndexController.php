<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assets;

use App\Actions\Input\FetchInputData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __construct(
        private readonly FetchInputData $fetchInputData,
    ) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('InputData', $this->fetchInputData->run($request));
    }
}
