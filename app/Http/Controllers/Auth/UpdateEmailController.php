<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateEmailRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UpdateEmailController extends Controller
{
    public function __invoke(UpdateEmailRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['email' => $request->string('email')->toString()]);

        return redirect()->back()->with('success', 'Email aggiornata.');
    }
}
