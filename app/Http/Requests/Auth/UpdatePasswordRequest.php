<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            // Same rule as SetupRequest: the UI must not be a way to weaken the
            // password below what first-run setup accepts.
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'La password attuale non è corretta.',
        ];
    }
}
