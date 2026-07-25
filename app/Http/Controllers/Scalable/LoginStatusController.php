<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scalable;

use App\Http\Controllers\Controller;
use App\Services\Scalable\ScalableLoginState;
use Illuminate\Http\JsonResponse;

class LoginStatusController extends Controller
{
    public function __construct(
        private readonly ScalableLoginState $state,
    ) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->state->snapshot());
    }
}
