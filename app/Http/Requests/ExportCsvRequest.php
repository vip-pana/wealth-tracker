<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function categoryId(): ?int
    {
        return $this->integer('category_id') ?: null;
    }

    public function dateFrom(): ?string
    {
        return $this->has('date_from') ? $this->string('date_from')->value() : null;
    }

    public function dateTo(): ?string
    {
        return $this->has('date_to') ? $this->string('date_to')->value() : null;
    }
}
