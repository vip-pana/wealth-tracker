<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashflow\UpdateEmergencyFundRequest;
use App\Models\InvestorProfile;
use Illuminate\Http\RedirectResponse;

class UpdateEmergencyFundController extends Controller
{
    public function __invoke(UpdateEmergencyFundRequest $request): RedirectResponse
    {
        /** @var int $months */
        $months = $request->validated('target_months');

        $profile = InvestorProfile::query()->firstOrNew([]);
        $profile->emergency_fund_months = $months;
        $profile->save();

        return redirect()->back();
    }
}
