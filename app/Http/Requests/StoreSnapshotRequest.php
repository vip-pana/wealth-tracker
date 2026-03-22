<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'month' => 'required|date_format:Y-m-d',
        ];
    }

    public function month(): string
    {
        return $this->string('month')->value();
    }
}
