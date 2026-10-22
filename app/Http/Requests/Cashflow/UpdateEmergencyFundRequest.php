<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Requests\Cashflow;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmergencyFundRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'target_months' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }
}
