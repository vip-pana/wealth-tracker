<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use App\Enums\MacroCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->withoutTrashed()],
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'nullable|integer|min:0',
            'macro_category' => ['nullable', Rule::enum(MacroCategory::class)],
        ];
    }
}
