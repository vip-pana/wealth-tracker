<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Http\Controllers\Controller;
use App\Models\BankConnection;
use Illuminate\Http\RedirectResponse;

class DisconnectController extends Controller
{
    public function __invoke(BankConnection $connection): RedirectResponse
    {
        // Cascades to the linked bank_accounts; the assets themselves stay,
        // they just stop being auto-synced.
        $connection->delete();

        return redirect()->route('settings.index')->with('success', 'Collegamento bancario rimosso.');
    }
}
