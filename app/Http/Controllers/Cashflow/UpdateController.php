<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashflow\UpdateBankTransactionsRequest;
use App\Models\BankTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class UpdateController extends Controller
{
    public function __invoke(UpdateBankTransactionsRequest $request): RedirectResponse
    {
        /** @var list<array{id: int, flow_type: string, excluded: bool}> $changes */
        $changes = $request->validated('changes') ?? [];

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

        $reviewed = 0;
        $month = $request->validated('month');

        if (is_string($month)) {
            $start = Carbon::parse($month)->startOfMonth();

            // Every row still pending in that month, in one statement — including
            // the ones an active filter was hiding. Rows already reviewed keep
            // their original timestamp, so it stays the date of the first look.
            $reviewed = BankTransaction::query()
                ->whereBetween('booking_date', [$start->format('Y-m-d'), $start->copy()->endOfMonth()->format('Y-m-d')])
                ->whereNull('reviewed_at')
                ->update(['reviewed_at' => now()]);
        }

        return redirect()->back()->with('success', $this->summary(count($changes), $reviewed));
    }

    private function summary(int $changed, int $reviewed): string
    {
        $parts = [];

        if ($changed > 0) {
            $parts[] = $changed.' '.($changed === 1 ? 'transazione aggiornata' : 'transazioni aggiornate');
        }

        if ($reviewed > 0) {
            $parts[] = $reviewed.' '.($reviewed === 1 ? 'segnata come rivista' : 'segnate come riviste');
        }

        return $parts === [] ? 'Nessuna modifica da salvare.' : ucfirst(implode(', ', $parts)).'.';
    }
}
