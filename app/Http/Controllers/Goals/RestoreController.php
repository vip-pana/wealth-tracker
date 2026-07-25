<?php

declare(strict_types=1);

namespace App\Http\Controllers\Goals;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;

class RestoreController extends Controller
{
    public function __invoke(int $goal): RedirectResponse
    {
        Goal::onlyTrashed()->findOrFail($goal)->restore();

        return redirect()->route('goal.index')->with('success', 'Obiettivo ripristinato.');
    }
}
