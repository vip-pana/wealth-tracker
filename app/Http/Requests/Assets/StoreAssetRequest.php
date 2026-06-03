<?php

declare(strict_types=1);

namespace App\Http\Requests\Assets;

use App\Models\BankAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $name = $this->input('name');
            $categoryId = $this->input('category_id');

            if (! is_string($name) || (! is_string($categoryId) && ! is_int($categoryId))) {
                return;
            }

            if (in_array($name.'|'.$categoryId, BankAccount::activeLinkKeys(), true)) {
                $validator->errors()->add('name', 'Esiste già un conto bancario collegato a questo nome e categoria: il saldo è gestito dalla banca. Scollega il conto in Impostazioni per inserirlo a mano.');
            }
        });
    }
}
