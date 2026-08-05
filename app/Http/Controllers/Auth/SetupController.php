<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetupRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Setup');
    }

    public function store(SetupRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string} $data */
        $data = $request->validated();

        $user = User::create($data);

        // Log the new account straight in: asking for the password one line
        // after choosing it is friction with no security value.
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Account creato. Benvenuto.');
    }
}
