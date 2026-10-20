<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Auth\SetupToken;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SetupRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $token = $this->container->make(SetupToken::class);

        // No name: nothing in the app ever displays one, so asking for it would
        // be a field the user fills in and no screen reads. The column stays
        // (it is NOT NULL in Laravel's default migration) and is filled in by
        // the controller.
        return [
            // Proof that the person creating the owner account is the person who
            // installed the app, not whoever reached /setup first. Validated
            // here rather than in middleware so a wrong code comes back as a
            // field error on the form the user is already looking at.
            'token' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($token): void {
                    if (! $token->verify(is_string($value) ? $value : null)) {
                        $fail('Codice di installazione non valido.');
                    }
                },
            ],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // This one password guards the entire financial history, and there
            // is no recovery flow behind it, so it is held to a real minimum
            // rather than Laravel's default eight characters.
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ];
    }
}
