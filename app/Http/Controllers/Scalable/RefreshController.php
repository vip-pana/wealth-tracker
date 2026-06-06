<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scalable;

use App\Actions\Prices\FetchScalableBalance;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class RefreshController extends Controller
{
    public function __construct(
        private readonly FetchScalableBalance $fetchScalableBalance,
    ) {}

    public function __invoke(): RedirectResponse
    {
        $result = $this->fetchScalableBalance->run();

        if ($result->hasFailures()) {
            return redirect()->back()->with('error', 'Sincronizzazione Scalable non riuscita. Verifica che il proxy sul Mac sia attivo e la sessione valida.');
        }

        if ($result->nothingUpdated()) {
            return redirect()->back()->with('error', 'Sincronizzazione Scalable non configurata.');
        }

        return redirect()->back()->with('success', sprintf('Saldi Scalable aggiornati (%d).', $result->updatedCount()));
    }
}
