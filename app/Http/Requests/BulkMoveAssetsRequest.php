<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkMoveAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['integer', 'exists:assets,id'],
            'target_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<int, int> */
    public function assetIds(): array
    {
        /** @var array<int, int> $ids */
        $ids = $this->validated('asset_ids', []);

        return $ids;
    }

    public function targetDate(): string
    {
        return $this->string('target_date')->value();
    }
}
