<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PriceFetcherService;
use Illuminate\Http\RedirectResponse;

class PriceController extends Controller
{
    public function refresh(PriceFetcherService $fetcher): RedirectResponse
    {
        $fetcher->fetchAll();

        return redirect()->back()->with('success', 'Prezzi aggiornati.');
    }
}
