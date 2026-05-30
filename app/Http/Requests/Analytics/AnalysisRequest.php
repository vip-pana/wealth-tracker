<?php

declare(strict_types=1);

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class AnalysisRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
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
