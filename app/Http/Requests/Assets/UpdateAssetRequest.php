<?php

declare(strict_types=1);

namespace App\Http\Requests\Assets;

use App\Models\Asset;
use App\Models\BankAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'ticker' => 'nullable|string|max:30',
            'isin' => 'nullable|string|max:12',
            'wallet_address' => 'nullable|string|max:255',
            'quantity' => 'nullable|numeric|min:0',
            'value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Asset $asset */
            $asset = $this->route('asset');

            // Already bank-managed: the controller strips identity/value
            // changes silently (the UI disables them), so no error here.
            if (in_array($asset->name.'|'.$asset->category_id, BankAccount::activeLinkKeys(), true)) {
                return;
            }

            // Renaming/recategorizing a free asset INTO a managed identity would
            // silently convert it to bank-managed and discard the value — block it.
            $name = $this->input('name', $asset->name);
            $categoryId = $this->input('category_id', $asset->category_id);

            if (! is_string($name) || (! is_string($categoryId) && ! is_int($categoryId))) {
                return;
            }

            if (in_array($name.'|'.$categoryId, BankAccount::activeLinkKeys(), true)) {
                $validator->errors()->add('name', 'Esiste già un conto bancario collegato a questo nome e categoria: il saldo è gestito dalla banca. Scollega il conto in Impostazioni per usarlo a mano.');
            }
        });
    }
}
