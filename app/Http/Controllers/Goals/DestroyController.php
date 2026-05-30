<?php

declare(strict_types=1);

namespace App\Http\Controllers\Goals;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;

class DestroyController extends Controller
{
    public function __invoke(Goal $goal): RedirectResponse
    {
        $goal->delete();

        return redirect()->route('goal.index')->with('success', 'Obiettivo eliminato.');
    }
}
