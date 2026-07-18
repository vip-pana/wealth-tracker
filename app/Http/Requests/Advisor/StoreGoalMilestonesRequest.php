<?php

declare(strict_types=1);

namespace App\Http\Requests\Advisor;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalMilestonesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'milestones' => ['required', 'array', 'min:1'],
            'milestones.*.notes' => ['nullable', 'string', 'max:100'],
            'milestones.*.action' => ['nullable', 'string', 'max:500'],
            'milestones.*.rationale' => ['nullable', 'string', 'max:800'],
            'milestones.*.target_value' => ['required', 'numeric', 'min:0'],
            'milestones.*.target_date' => ['required', 'date'],
            'milestones.*.allocation' => ['nullable', 'array'],
            'milestones.*.allocation.*.category' => ['required', 'string', 'exists:categories,name'],
            'milestones.*.allocation.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
