<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\SetupToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetupRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function __construct(
        private readonly SetupToken $token,
    ) {}

    public function create(): Response
    {
        // Rendering the page mints the token if this install has never had one,
        // so the code is already on disk and in the log by the time the form
        // asks for it. The page is told where to look, never what the token is.
        $this->token->current();

        return Inertia::render('Auth/Setup', ['tokenPath' => $this->token->path()]);
    }

    public function store(SetupRequest $request): RedirectResponse
    {
        /** @var array{email: string, password: string} $data */
        $data = $request->safe()->only(['email', 'password']);

        // `name` is NOT NULL in the default users migration but nothing reads
        // it, so it is filled rather than asked for.
        $user = User::create([...$data, 'name' => 'Owner']);

        // The door it guarded is now bricked up by RequireSetup, so the token is
        // a live secret with nothing left to protect. Delete it rather than
        // leave it sitting in the data directory that gets backed up nightly.
        $this->token->forget();

        // Log the new account straight in: asking for the password one line
        // after choosing it is friction with no security value.
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Account creato. Benvenuto.');
    }
}
