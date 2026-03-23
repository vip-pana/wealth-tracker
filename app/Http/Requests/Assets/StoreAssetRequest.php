<?php

declare(strict_types=1);

namespace App\Http\Requests\Assets;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'ticker' => 'nullable|string|max:30',
            'wallet_address' => 'nullable|string|max:255',
            'quantity' => 'nullable|numeric|min:0|required_with:ticker',
            'value' => 'required_without:ticker|nullable|numeric|min:0',
            'date' => 'required|date_format:Y-m-d',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
