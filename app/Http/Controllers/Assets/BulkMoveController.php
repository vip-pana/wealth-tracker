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
        Asset::whereIn('id', $request->assetIds())
            ->update(['date' => $request->targetDate()]);

        return redirect()->back()->with('success', 'Asset spostati.');
    }
}
