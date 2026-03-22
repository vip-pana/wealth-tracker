<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\CopyAssetsRequest;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;

class CopyFromMonthController extends Controller
{
    public function __invoke(CopyAssetsRequest $request): RedirectResponse
    {
        Asset::whereDate('date', $request->sourceDate())
            ->get()
            ->each(
                fn (Asset $asset): mixed => Asset::create([
                    'category_id' => $asset->category_id,
                    'name' => $asset->name,
                    'ticker' => $asset->ticker,
                    'wallet_address' => $asset->wallet_address,
                    'quantity' => $asset->quantity,
                    'value' => $asset->value,
                    'date' => $request->targetDate(),
                    'notes' => $asset->notes,
                ])
            );

        return redirect()->back()->with('success', 'Asset copiati.');
    }
}
