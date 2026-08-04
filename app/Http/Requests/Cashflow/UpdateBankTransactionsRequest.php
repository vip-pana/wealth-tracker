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
            // Either axis alone is a valid submission: correcting rows, or just
            // saying "I have been through this month". Agreeing with the
            // classifier changes nothing, so review-only is the common case.
            'changes' => ['array', 'required_without:month'],
            'changes.*.id' => ['required', 'integer', 'exists:bank_transactions,id'],
            'changes.*.flow_type' => ['required', Rule::in([
                BankTransaction::FLOW_INCOME,
                BankTransaction::FLOW_EXPENSE,
                BankTransaction::FLOW_TRANSFER,
            ])],
            'changes.*.excluded' => ['required', 'boolean'],
            // The month to mark reviewed. The server derives the rows from it
            // rather than trusting a list of ids built under an active filter,
            // so nothing is left behind unnoticed.
            'month' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
