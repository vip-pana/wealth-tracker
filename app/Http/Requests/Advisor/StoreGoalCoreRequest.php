<?php

declare(strict_types=1);

namespace App\Http\Requests\Advisor;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalCoreRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'target_value' => ['required', 'numeric', 'min:0'],
            'target_date' => ['nullable', 'date'],
        ];
    }
}
