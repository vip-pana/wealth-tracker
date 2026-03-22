<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkMoveAssetsRequest;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;

class BulkMoveController extends Controller
{
    public function __invoke(BulkMoveAssetsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Asset::whereIn('id', $validated['asset_ids'])
            ->update(['date' => $validated['target_date']]);

        return redirect()->back()->with('success', 'Asset spostati.');
    }
}
