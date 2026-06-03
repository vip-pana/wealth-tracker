<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Http\Clients\EnableBankingClient;
use App\Http\Controllers\Controller;
use App\Models\BankConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ConnectController extends Controller
{
    public function __construct(
        private readonly EnableBankingClient $enableBanking,
    ) {}

    public function __invoke(Request $request): Response
    {
        $bank = $request->string('aspsp_name')->value();
        $country = $request->string('aspsp_country')->value();

        if ($bank === '' || $country === '') {
            return redirect()->route('settings.index')->with('error', 'Banca non valida.');
        }

        $state = Str::random(40);
        $redirectUrl = Config::string('services.enable_banking.redirect_url', '');

        $auth = $this->enableBanking->startAuthorization($bank, $country, $redirectUrl, $state);

        if ($auth === null) {
            return redirect()->route('settings.index')
                ->with('error', 'Impossibile avviare il collegamento bancario. Riprova.');
        }

        // Drop only stale *pending* attempts for this bank (abandoned consents).
        // Expired/active ones are kept until the callback succeeds, so it can
        // inherit their account→asset links by IBAN, then prune them.
        BankConnection::where('aspsp_name', $bank)
            ->where('aspsp_country', $country)
            ->where('status', BankConnection::STATUS_PENDING)
            ->delete();

        BankConnection::create([
            'aspsp_name' => $bank,
            'aspsp_country' => $country,
            'state' => $state,
            'status' => BankConnection::STATUS_PENDING,
        ]);

        // Send the user to the bank's consent page with a full-page redirect.
        // Inertia::location triggers window.location instead of an XHR follow,
        // which a cross-origin bank URL would block via CORS.
        return Inertia::location($auth['url']);
    }
}
