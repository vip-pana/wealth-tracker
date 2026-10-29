<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Advisor\AdvisorOutputFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advisor\StoreInvestorProfileRequest;
use App\Models\InvestorProfile;
use Illuminate\Http\RedirectResponse;

class StoreProfileController extends Controller
{
    public function __construct(private readonly AdvisorOutputFilter $filter) {}

    public function __invoke(StoreInvestorProfileRequest $request): RedirectResponse
    {
        // Single-row profile: update the existing record or create the first.
        $profile = InvestorProfile::query()->firstOrNew([]);

        $data = $request->validated();

        // `notes` is the model's own synthesis of the profiling interview,
        // posted back from the proposal card and then shown on the Goal page
        // for good — outside the advisor and outside its footer. Same reason
        // the milestone fields are screened here, and the same place: the
        // confirmation click applies the model's text, it does not vouch for
        // it. `name` and `risk_tolerance` are not free text and are left
        // alone. The read side screens too, for rows written before this.
        if (isset($data['notes']) && is_string($data['notes'])) {
            $data['notes'] = $this->filter->filterField($data['notes']);
        }

        $profile->fill($data);
        $profile->save();

        return redirect()->back()->with('success', 'Profilo investitore salvato.');
    }
}
