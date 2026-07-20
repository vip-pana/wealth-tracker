<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashflow;

use App\Models\BankTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankTransactionsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'changes' => ['required', 'array', 'min:1'],
            'changes.*.id' => ['required', 'integer', 'exists:bank_transactions,id'],
            'changes.*.flow_type' => ['required', Rule::in([
                BankTransaction::FLOW_INCOME,
                BankTransaction::FLOW_EXPENSE,
                BankTransaction::FLOW_TRANSFER,
            ])],
            'changes.*.excluded' => ['required', 'boolean'],
        ];
    }
}
