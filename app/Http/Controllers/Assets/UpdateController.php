<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assets\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;

class UpdateController extends Controller
{
    public function __invoke(UpdateAssetRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->validated();

        // Mirror the UI lock server-side: an asset managed by an active bank
        // link owns its identity (name + category) and value via the account,
        // so a request must not change those — it would silently break the
        // link the bank sync resolves on. Other fields (e.g. notes) are free.
        if (in_array($asset->name.'|'.$asset->category_id, BankAccount::activeLinkKeys(), true)) {
            unset($data['name'], $data['category_id'], $data['value']);
        }

        $asset->update($data);

        return redirect()->back()->with('success', 'Asset aggiornato.');
    }
}
