<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'name' => 'sometimes|string|max:100|unique:categories,name,'.$category->id,
            'color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
