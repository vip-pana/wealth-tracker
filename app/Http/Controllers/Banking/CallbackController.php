<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Http\Clients\EnableBankingClient;
use App\Http\Controllers\Controller;
use App\Models\BankConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CallbackController extends Controller
{
    public function __construct(
        private readonly EnableBankingClient $enableBanking,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $code = $request->string('code')->value();
        $state = $request->string('state')->value();

        $connection = BankConnection::where('state', $state)
            ->where('status', BankConnection::STATUS_PENDING)
            ->first();

        if ($connection === null || $code === '') {
            return redirect()->route('settings.index')->with('error', 'Collegamento bancario non riconosciuto o scaduto.');
        }

        $session = $this->enableBanking->authorizeSession($code);

        if ($session === null) {
            return redirect()->route('settings.index')->with('error', 'Autorizzazione bancaria fallita. Riprova.');
        }

        $connection->update([
            'session_id' => $session['session_id'],
            'status' => BankConnection::STATUS_ACTIVE,
            // The access window we requested in /auth; banks cap it (often ~90 days).
            'valid_until' => Carbon::now()->addDays(90),
        ]);

        foreach ($session['accounts'] as $account) {
            $uid = $account['uid'] ?? null;
            if (! is_string($uid)) {
                continue;
            }

            $accountId = $account['account_id'] ?? null;
            $iban = is_array($accountId) ? ($accountId['iban'] ?? null) : null;
            $name = $account['name'] ?? null;
            $currency = $account['currency'] ?? null;

            $connection->accounts()->create([
                'uid' => $uid,
                'iban' => is_string($iban) ? $iban : null,
                'name' => is_string($name) ? $name : null,
                'currency' => is_string($currency) ? $currency : null,
            ]);
        }

        return redirect()->route('settings.index')
            ->with('success', sprintf('Conto bancario collegato: %s (%d conti).', $connection->aspsp_name, $connection->accounts()->count()));
    }
}
