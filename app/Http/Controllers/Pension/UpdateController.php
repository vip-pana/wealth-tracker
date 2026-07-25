<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pension;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pension\UpdatePensionRequest;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class UpdateController extends Controller
{
    public function __invoke(UpdatePensionRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->validated();

        if ($request->has('year')) {
            $year = $request->integer('year');
            $data['date'] = Carbon::createFromDate($year, 12, 31)->format('Y-m-d');
            unset($data['year']);
        }

        $asset->update($data);

        return redirect()->back()->with('success', 'Valore fondo pensione aggiornato.');
    }
}
