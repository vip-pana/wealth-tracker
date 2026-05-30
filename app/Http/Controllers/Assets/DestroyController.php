<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;

class DestroyController extends Controller
{
    public function __invoke(Asset $asset): RedirectResponse
    {
        $asset->delete();

        return redirect()->back()
            ->with('success', 'Asset eliminato.')
            ->with('undo', route('assets.restore', $asset->id, absolute: false));
    }
}
