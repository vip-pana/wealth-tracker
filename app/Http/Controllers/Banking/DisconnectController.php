<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\BankConnection;
use Illuminate\Http\RedirectResponse;

class DisconnectController extends Controller
{
    public function __invoke(BankConnection $connection): RedirectResponse
    {
        // The assets stay but stop being auto-synced and become manual again.
        // Clear bank_synced_at on their rows so the "Banca" badge/freshness no
        // longer implies a live link that no longer exists.
        $connection->loadMissing('accounts');
        foreach ($connection->accounts as $account) {
            if ($account->linked_name === null || $account->linked_category_id === null) {
                continue;
            }
            Asset::where('name', $account->linked_name)
                ->where('category_id', $account->linked_category_id)
                ->whereNotNull('bank_synced_at')
                ->update(['bank_synced_at' => null]);
        }

        // Cascades to the linked bank_accounts.
        $connection->delete();

        return redirect()->route('settings.index')
            ->with('success', 'Collegamento bancario rimosso. I valori dei conti collegati ora sono manuali e non si aggiornano più automaticamente.');
    }
}
