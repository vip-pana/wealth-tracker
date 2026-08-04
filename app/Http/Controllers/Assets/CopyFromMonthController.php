<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assets\CopyAssetsRequest;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;

class CopyFromMonthController extends Controller
{
    public function __invoke(CopyAssetsRequest $request): RedirectResponse
    {
        $assetIds = $request->assetIds();
        $targetDate = $request->targetDate();

        // Copying an asset that already exists in the target month would create
        // a duplicate row for the same "category and name" pair — the very
        // thing the picker exists to prevent. The ids come from the client, so
        // a stale list or a double submit can ask for exactly that.
        $alreadyPresent = Asset::whereDate('date', $targetDate)
            ->get()
            ->map(fn (Asset $a): string => $a->category_id.'|'.$a->name)
            ->all();

        Asset::whereDate('date', $request->sourceDate())
            ->when($assetIds !== null, fn ($query) => $query->whereIn('id', $assetIds))
            ->get()
            ->reject(fn (Asset $asset): bool => in_array($asset->category_id.'|'.$asset->name, $alreadyPresent, true))
            ->each(
                fn (Asset $asset): mixed => Asset::create([
                    'category_id' => $asset->category_id,
                    'name' => $asset->name,
                    'ticker' => $asset->ticker,
                    'isin' => $asset->isin,
                    'wallet_address' => $asset->wallet_address,
                    'quantity' => $asset->quantity,
                    'value' => $asset->value,
                    'date' => $targetDate,
                    'notes' => $asset->notes,
                ])
            );

        return redirect()->back()->with('success', 'Asset copiati.');
    }
}
