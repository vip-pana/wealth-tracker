<?php

declare(strict_types=1);

namespace App\Http\Requests\Goals;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoalRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'target_value' => ['required', 'numeric', 'min:0'],
            'target_date' => ['nullable', 'date'],
            'category_allocations' => ['nullable', 'array'],
            'category_allocations.*.category_id' => ['required', 'integer', 'exists:categories,id'],
            'category_allocations.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'macro_allocations' => ['nullable', 'array'],
            'macro_allocations.*.macro_category' => ['required', 'string', 'in:Liquidità,ETF,Cripto'],
            'macro_allocations.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'milestones' => ['nullable', 'array'],
            'milestones.*.notes' => ['nullable', 'string'],
            'milestones.*.target_value' => ['required', 'numeric', 'min:0'],
            'milestones.*.target_date' => ['required', 'date'],
        ];
    }
}
