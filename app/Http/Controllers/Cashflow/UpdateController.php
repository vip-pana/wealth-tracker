<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashflow\UpdateBankTransactionsRequest;
use App\Models\BankTransaction;
use Illuminate\Http\RedirectResponse;

class UpdateController extends Controller
{
    public function __invoke(UpdateBankTransactionsRequest $request): RedirectResponse
    {
        /** @var list<array{id: int, flow_type: string, excluded: bool}> $changes */
        $changes = $request->validated('changes');

        foreach ($changes as $change) {
            // Pinning a transaction marks it manual, so the daily auto-classifier
            // leaves it alone from now on: manual always wins.
            BankTransaction::query()
                ->whereKey($change['id'])
                ->update([
                    'flow_type' => $change['flow_type'],
                    'excluded' => $change['excluded'],
                    'is_manual' => true,
                ]);
        }

        return redirect()->back()->with('success', count($changes).' transazioni aggiornate.');
    }
}
