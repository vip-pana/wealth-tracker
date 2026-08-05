<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SetupRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // This one password guards the entire financial history, and there
            // is no recovery flow behind it, so it is held to a real minimum
            // rather than Laravel's default eight characters.
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ];
    }
}
