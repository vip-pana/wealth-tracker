<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use App\Enums\MacroCategory;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category->id)->withoutTrashed()],
            'color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'nullable|integer|min:0',
            'macro_category' => ['nullable', Rule::enum(MacroCategory::class)],
        ];
    }
}
