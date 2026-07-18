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
            'horizon' => ['nullable', 'in:short,medium,long'],
            'risk_tolerance' => ['nullable', 'in:low,medium,high'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
