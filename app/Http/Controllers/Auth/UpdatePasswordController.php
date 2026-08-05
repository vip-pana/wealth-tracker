<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class UpdatePasswordController extends Controller
{
    public function __invoke(UpdatePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // The cast hashes the password. remember_token has to go through
        // forceFill: it is not in $fillable, so update() would drop it
        // silently and leave the long-lived "remember me" cookie valid.
        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'remember_token' => null,
        ])->save();

        // Revoking access elsewhere is the point of changing the password here:
        // a lost phone holds a valid session for up to 30 days. Auth's
        // logoutOtherDevices() is not used — it is a silent no-op without the
        // AuthenticateSession middleware, which this app does not run — so the
        // other sessions are deleted directly, keeping the current one alive.
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return redirect()->back()->with('success', 'Password aggiornata. Ogni altro dispositivo è stato disconnesso.');
    }
}
