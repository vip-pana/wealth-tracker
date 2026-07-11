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
            'macro_allocations' => ['required', 'array', 'min:1'],
            'macro_allocations.*.macro_category' => ['required', 'string', 'in:Liquidità,ETF,Cripto'],
            'macro_allocations.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
