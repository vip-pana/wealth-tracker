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
        $result = $this->fetchAllPrices->run();

        if ($result->nothingUpdated() && $result->hasFailures()) {
            return redirect()->back()->with('error', 'Nessun valore aggiornato. Riprova più tardi.');
        }

        if ($result->hasFailures()) {
            return redirect()->back()->with('error', sprintf(
                'Aggiornati %d. Non riusciti: %s.',
                $result->updatedCount(),
                implode(', ', $result->failed),
            ));
        }

        return redirect()->back()->with('success', sprintf('Saldi e prezzi aggiornati (%d).', $result->updatedCount()));
    }
}
