<?php

declare(strict_types=1);

namespace App\Http\Controllers\Prices;

use App\Http\Controllers\Controller;
use App\Services\PriceFetcherService;
use Illuminate\Http\RedirectResponse;

class RefreshController extends Controller
{
    public function __invoke(PriceFetcherService $fetcher): RedirectResponse
    {
        $fetcher->fetchAll();

        return redirect()->back()->with('success', 'Prezzi aggiornati.');
    }
}
