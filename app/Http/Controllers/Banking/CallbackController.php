<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Actions\Notifications\PushNotification;
use App\Actions\Prices\FetchBankBalances;
use App\Http\Clients\EnableBankingClient;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CallbackController extends Controller
{
    public function __construct(
        private readonly EnableBankingClient $enableBanking,
        private readonly PushNotification $notify,
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
            // Prefer the real validity the bank reports; some cap it below the 90
            // days we requested. Fall back to 90 days only if it's omitted.
            'valid_until' => $session['valid_until'] ?? Carbon::now()->addDays(90),
        ]);

        // Reconnecting the same bank: the account uid changes each session but the
        // IBAN is stable, so carry the prior account→asset links over by IBAN.
        $priorLinks = $this->priorLinksByIban($connection);

        foreach ($session['accounts'] as $account) {
            $uid = $account['uid'] ?? null;
            if (! is_string($uid)) {
                continue;
            }

            $accountId = $account['account_id'] ?? null;
            $iban = is_array($accountId) ? ($accountId['iban'] ?? null) : null;
            $iban = is_string($iban) ? $iban : null;
            $name = $account['name'] ?? null;
            $currency = $account['currency'] ?? null;
            $inherited = $iban !== null ? ($priorLinks[$iban] ?? null) : null;

            $connection->accounts()->create([
                'uid' => $uid,
                'iban' => $iban,
                'name' => is_string($name) ? $name : null,
                'currency' => is_string($currency) ? $currency : null,
                'linked_name' => $inherited['linked_name'] ?? null,
                'linked_category_id' => $inherited['linked_category_id'] ?? null,
            ]);
        }

        // Now that links are inherited, drop the superseded connections for this
        // bank (everything except the one we just activated). Reconnecting
        // resolves any standing "consent expired" warning for those — the keys
        // are per superseded connection id, so clear them before deleting.
        $superseded = BankConnection::where('aspsp_name', $connection->aspsp_name)
            ->where('aspsp_country', $connection->aspsp_country)
            ->whereKeyNot($connection->id)
            ->get();

        foreach ($superseded as $old) {
            $this->notify->resolve(FetchBankBalances::consentExpiredKey($old->id));
            $old->delete();
        }

        return redirect()->route('settings.index')
            ->with('success', sprintf('Conto bancario collegato: %s (%d conti).', $connection->aspsp_name, $connection->accounts()->count()));
    }

    /**
     * Map of IBAN → prior asset link, for other connections of the same bank.
     *
     * @return array<string, array{linked_name: string|null, linked_category_id: int|null}>
     */
    private function priorLinksByIban(BankConnection $current): array
    {
        $map = [];

        $accounts = BankAccount::query()
            ->whereNotNull('iban')
            ->whereNotNull('linked_name')
            ->whereHas('connection', fn ($q) => $q
                ->where('aspsp_name', $current->aspsp_name)
                ->where('aspsp_country', $current->aspsp_country)
                ->whereKeyNot($current->id)
            )
            ->get();

        foreach ($accounts as $account) {
            if ($account->iban !== null) {
                $map[$account->iban] = [
                    'linked_name' => $account->linked_name,
                    'linked_category_id' => $account->linked_category_id,
                ];
            }
        }

        return $map;
    }
}
