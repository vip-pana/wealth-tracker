<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Http\Controllers\Controller;
use App\Models\Asset;
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

        // The dropdown picks a concrete asset row, but we store the logical
        // identity (name + category) so the link follows that asset across
        // monthly rows rather than pinning to one month's id.
        if (empty($validated['asset_id'])) {
            $account->update(['linked_name' => null, 'linked_category_id' => null]);
        } else {
            /** @var Asset $asset */
            $asset = Asset::query()->findOrFail($validated['asset_id']);
            $account->update(['linked_name' => $asset->name, 'linked_category_id' => $asset->category_id]);
        }

        return redirect()->route('settings.index')->with('success', 'Conto collegato.');
    }
}
