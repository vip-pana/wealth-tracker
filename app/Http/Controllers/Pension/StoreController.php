<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pension;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pension\StorePensionRequest;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class StoreController extends Controller
{
    public function __invoke(StorePensionRequest $request): RedirectResponse
    {
        $year = $request->integer('year');
        $date = Carbon::createFromDate($year, 12, 31)->format('Y-m-d');

        Asset::create([
            'category_id' => $request->integer('category_id'),
            'name' => $request->string('name')->value(),
            'value' => $request->float('value'),
            'date' => $date,
            'notes' => $request->input('notes'),
        ]);

        return redirect()->back()->with('success', 'Valore fondo pensione aggiunto.');
    }
}
