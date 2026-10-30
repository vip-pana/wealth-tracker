<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Advisor\AdvisorOutputFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advisor\StoreGoalCoreRequest;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;

class StoreGoalCoreController extends Controller
{
    public function __construct(private readonly AdvisorOutputFilter $filter) {}

    public function __invoke(StoreGoalCoreRequest $request): RedirectResponse
    {
        // Single-goal app: update the existing goal or create the first one. The
        // goal table requires a name, so when none is proposed (the advisor may
        // only settle amount/date/why) we default it from the description.
        $goal = Goal::query()->firstOrNew([]);

        // Both fields are the model's words, posted back from the goal proposal
        // card and then shown on the Goal page for good. Screened before the
        // name is derived from the description, so the derivation cannot carry
        // through text the filter would have removed.
        $name = $this->screen($request->string('name')->value());
        $description = $this->screen($request->string('description')->value());
        $goal->fill([
            'name' => $name ?? $goal->name ?? ($description !== null ? mb_substr($description, 0, 100) : 'Il mio obiettivo'),
            'description' => $description ?? $goal->description,
            'target_value' => $request->float('target_value'),
            'target_date' => $request->string('target_date')->value() ?: null,
        ]);
        $goal->save();

        return redirect()->back()->with('success', 'Obiettivo salvato.');
    }

    private function screen(string $value): ?string
    {
        return $value === '' ? null : $this->filter->filterField($value);
    }
}
