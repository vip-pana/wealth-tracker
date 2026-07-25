<?php

declare(strict_types=1);

namespace App\Http\Requests\Advisor;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalCompositionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.category' => ['required', 'string', 'exists:categories,name'],
            'allocations.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
