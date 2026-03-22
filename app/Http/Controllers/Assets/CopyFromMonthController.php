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
        $sourceDate = $request->string('source_date')->value();
        $targetDate = $request->string('month', now()->format('Y-m-01'))->value();

        Asset::whereDate('date', $sourceDate)
            ->get()
            ->each(function (Asset $asset) use ($targetDate): void {
                Asset::create([
                    'category_id' => $asset->category_id,
                    'name' => $asset->name,
                    'ticker' => $asset->ticker,
                    'wallet_address' => $asset->wallet_address,
                    'quantity' => $asset->quantity,
                    'value' => $asset->value,
                    'date' => $targetDate,
                    'notes' => $asset->notes,
                ]);
            });

        return redirect()->back()->with('success', 'Asset copiati.');
    }
}
