<?php

declare(strict_types=1);

namespace App\Http\Requests\Assets;

use Illuminate\Foundation\Http\FormRequest;

class CopyAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_date' => ['required', 'date_format:Y-m-d'],
            'asset_ids' => ['sometimes', 'array'],
            'asset_ids.*' => ['integer', 'exists:assets,id'],
        ];
    }

    /**
     * Restrict the copy to these assets. Null means "every asset in the source
     * month", the behaviour before selective copying existed.
     *
     * @return list<int>|null
     */
    public function assetIds(): ?array
    {
        if (! $this->has('asset_ids')) {
            return null;
        }

        $ids = [];
        foreach ($this->collect('asset_ids') as $id) {
            // Validated as integer, so a scalar cast is safe here.
            $ids[] = (int) (is_scalar($id) ? $id : 0);
        }

        return $ids;
    }

    public function sourceDate(): string
    {
        return $this->string('source_date')->value();
    }

    public function targetDate(): string
    {
        return $this->string('month', now()->format('Y-m-01'))->value();
    }
}
