<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assets\StoreAssetRequest;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    public function __invoke(StoreAssetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['value'] ??= 0.0;

        Asset::create($data);

        return redirect()->back()->with('success', 'Asset aggiunto.');
    }
}
