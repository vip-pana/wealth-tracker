<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advisor\StoreGoalCoreRequest;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;

class StoreGoalCoreController extends Controller
{
    public function __invoke(StoreGoalCoreRequest $request): RedirectResponse
    {
        // Single-goal app: update the existing goal or create the first one. The
        // goal table requires a name, so when none is proposed (the advisor may
        // only settle amount/date/why) we default it from the description.
        $goal = Goal::query()->firstOrNew([]);

        $name = $request->string('name')->value() ?: null;
        $description = $request->string('description')->value() ?: null;
        $goal->fill([
            'name' => $name ?? $goal->name ?? ($description !== null ? mb_substr($description, 0, 100) : 'Il mio obiettivo'),
            'description' => $description ?? $goal->description,
            'target_value' => $request->float('target_value'),
            'target_date' => $request->string('target_date')->value() ?: null,
        ]);
        $goal->save();

        return redirect()->back()->with('success', 'Obiettivo salvato.');
    }
}
