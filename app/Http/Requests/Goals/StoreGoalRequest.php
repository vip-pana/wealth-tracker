<?php

declare(strict_types=1);

namespace App\Http\Requests\Goals;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'target_value' => ['required', 'numeric', 'min:0'],
            'target_date' => ['nullable', 'date', 'after:today'],
            'milestones' => ['nullable', 'array'],
            'milestones.*.notes' => ['nullable', 'string'],
            'milestones.*.action' => ['nullable', 'string', 'max:500'],
            'milestones.*.rationale' => ['nullable', 'string', 'max:800'],
            'milestones.*.target_value' => ['required', 'numeric', 'min:0'],
            'milestones.*.target_date' => ['required', 'date'],
            'milestones.*.allocation' => ['nullable', 'array'],
            'milestones.*.allocation.*.category_id' => ['required', 'integer', 'exists:categories,id'],
            'milestones.*.allocation.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
