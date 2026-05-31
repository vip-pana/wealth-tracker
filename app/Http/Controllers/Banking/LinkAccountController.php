<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LinkAccountController extends Controller
{
    public function __invoke(Request $request, BankAccount $account): RedirectResponse
    {
        $validated = $request->validate([
            'asset_id' => 'nullable|exists:assets,id',
        ]);

        $account->update(['asset_id' => $validated['asset_id'] ?? null]);

        return redirect()->route('settings.index')->with('success', 'Conto collegato.');
    }
}
