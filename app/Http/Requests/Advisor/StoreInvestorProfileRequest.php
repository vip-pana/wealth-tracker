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
            'horizon' => ['nullable', 'in:short,medium,long'],
            'risk_tolerance' => ['nullable', 'in:low,medium,high'],
            'income_monthly' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'memory' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
