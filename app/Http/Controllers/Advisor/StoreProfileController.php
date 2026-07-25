<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advisor\StoreInvestorProfileRequest;
use App\Models\InvestorProfile;
use Illuminate\Http\RedirectResponse;

class StoreProfileController extends Controller
{
    public function __invoke(StoreInvestorProfileRequest $request): RedirectResponse
    {
        // Single-row profile: update the existing record or create the first.
        $profile = InvestorProfile::query()->firstOrNew([]);
        $profile->fill($request->validated());
        $profile->save();

        return redirect()->back()->with('success', 'Profilo investitore salvato.');
    }
}
