<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scalable;

use App\Http\Clients\ScalableUnofficialClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class LoginController extends Controller
{
    public function __construct(
        private readonly ScalableUnofficialClient $scalable,
    ) {}

    public function __invoke(): RedirectResponse
    {
        if ($this->scalable->login()) {
            return redirect()->back()->with('success', 'Sessione Scalable collegata. Ora puoi sincronizzare i saldi.');
        }

        return redirect()->back()->with('error', 'Impossibile avviare il login Scalable. Verifica che il proxy sul Mac sia attivo, poi riprova.');
    }
}
