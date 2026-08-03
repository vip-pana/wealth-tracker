<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashflow;

use Illuminate\Foundation\Http\FormRequest;

class CashflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * The requested month, clamped to the current one: transactions can only
     * exist up to today, so a future month is always empty.
     */
    public function month(): string
    {
        $current = now()->format('Y-m-01');
        $month = $this->string('month', $current)->value();

        return $month > $current ? $current : $month;
    }
}
