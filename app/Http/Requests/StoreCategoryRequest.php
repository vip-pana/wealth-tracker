<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|unique:categories,name',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
