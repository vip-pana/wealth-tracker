<?php

declare(strict_types=1);

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ImportCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Seleziona un file CSV.',
            'file.mimes' => 'Il file deve essere in formato CSV.',
            'file.max' => 'Il file non può superare 2 MB.',
        ];
    }

    public function csvFile(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('file');
    }
}
