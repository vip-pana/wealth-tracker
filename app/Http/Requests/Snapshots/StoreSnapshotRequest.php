<?php

declare(strict_types=1);

namespace App\Http\Requests\Snapshots;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

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
            'date' => 'nullable|date_format:Y-m-d',
        ];
    }

    public function snapshotDate(): string
    {
        $date = $this->string('date')->value();

        return $date !== '' ? $date : Carbon::now()->toDateString();
    }
}
