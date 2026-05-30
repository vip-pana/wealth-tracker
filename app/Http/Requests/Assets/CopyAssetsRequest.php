<?php

declare(strict_types=1);

namespace App\Http\Requests\Assets;

use Illuminate\Foundation\Http\FormRequest;

class CopyAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function sourceDate(): string
    {
        return $this->string('source_date')->value();
    }

    public function targetDate(): string
    {
        return $this->string('month', now()->format('Y-m-01'))->value();
    }
}
