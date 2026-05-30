<?php

declare(strict_types=1);

namespace App\Http\Requests\Pension;

use App\Models\Category;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => 'sometimes|integer|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'value' => 'sometimes|numeric|min:0',
            'year' => 'sometimes|integer|min:1970|max:2100',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if (! $this->has('category_id')) {
                return;
            }
            $categoryId = $this->integer('category_id');
            if ($categoryId === 0) {
                return;
            }
            $category = Category::find($categoryId);
            if ($category === null || ! ($category->macro_category?->isIlliquid() ?? false)) {
                $v->errors()->add('category_id', 'La categoria selezionata non è un fondo pensione.');
            }
        });
    }
}
