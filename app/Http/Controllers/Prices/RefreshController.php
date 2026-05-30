<?php

declare(strict_types=1);

namespace App\Http\Controllers\Prices;

use App\Actions\Prices\FetchAllPrices;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class RefreshController extends Controller
{
    public function __construct(
        private readonly FetchAllPrices $fetchAllPrices,
    ) {}

    public function __invoke(): RedirectResponse
    {
        $this->fetchAllPrices->run();

        return redirect()->back()->with('success', 'Prezzi aggiornati.');
    }
}
