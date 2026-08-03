<?php

declare(strict_types=1);

namespace App\Http\Requests\Advisor;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestorProfileRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            // No `horizon`: it is derived from the goal's target date
            // (Goal::horizon()), so it is deliberately not writable here.
            'risk_tolerance' => ['nullable', 'in:low,medium,high'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'memory' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
