<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashflow;

use App\Actions\Transactions\ImportBankTransactions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class SyncController extends Controller
{
    public function __construct(
        private readonly ImportBankTransactions $import,
    ) {}

    /**
     * On-demand run of the same import the scheduler fires daily at 06:05.
     * The import is idempotent (keyed on the Enable Banking transaction id), so
     * pressing this between scheduled runs can only add rows, never duplicate.
     */
    public function __invoke(): RedirectResponse
    {
        $result = $this->import->run();

        if ($result['accounts'] === 0) {
            return redirect()->back()->with('error', 'Nessun conto sincronizzabile. Controlla i collegamenti bancari nelle Impostazioni.');
        }

        return redirect()->back()->with('success', sprintf(
            '%d transazioni sincronizzate su %d %s.',
            $result['imported'],
            $result['accounts'],
            $result['accounts'] === 1 ? 'conto' : 'conti',
        ));
    }
}
