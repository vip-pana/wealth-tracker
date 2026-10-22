<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Actions\Notifications\PushNotification;
use App\Actions\Prices\FetchBankBalances;
use App\Http\Clients\EnableBankingClient;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankConnection;
use Illuminate\Database\Eloquent\Collection;
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
        // account itself does not. Move the prior bank_accounts rows onto this
        // connection instead of creating new ones, so the asset link, the sync
        // state and the accumulated transaction history all survive the renewal.
        $prior = $this->priorAccounts($connection);

        foreach ($session['accounts'] as $account) {
            $uid = $account['uid'] ?? null;
            if (! is_string($uid)) {
                continue;
            }

            $accountId = $account['account_id'] ?? null;
            $iban = is_array($accountId) ? ($accountId['iban'] ?? null) : null;
            $name = $account['name'] ?? null;
            $currency = $account['currency'] ?? null;

            $attributes = [
                'bank_connection_id' => $connection->id,
                'uid' => $uid,
                'iban' => is_string($iban) ? $iban : null,
                'name' => is_string($name) ? $name : null,
                'currency' => is_string($currency) ? $currency : null,
            ];

            $match = $this->matchPrior($prior, $attributes['iban'], $attributes['name'], $attributes['currency']);

            if (! $match instanceof BankAccount) {
                $connection->accounts()->create($attributes);

                continue;
            }

            $match->update($attributes);
            $prior = $prior->reject(fn (BankAccount $a) => $a->is($match));
        }

        // Now that the accounts have moved over, drop the superseded connections
        // for this bank (everything except the one we just activated); only
        // accounts the new consent no longer covers go with them. Reconnecting
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
     * Accounts on the other connections of the same bank, which this consent
     * supersedes.
     *
     * @return Collection<int, BankAccount>
     */
    private function priorAccounts(BankConnection $current): Collection
    {
        return BankAccount::query()
            ->whereHas('connection', fn ($q) => $q
                ->where('aspsp_name', $current->aspsp_name)
                ->where('aspsp_country', $current->aspsp_country)
                ->whereKeyNot($current->id)
            )
            ->get();
    }

    /**
     * The prior account a freshly consented one continues. The IBAN is the
     * stable identity; a bank that omits it is matched by name and currency,
     * but only when that pair is unambiguous.
     *
     * @param  Collection<int, BankAccount>  $prior
     */
    private function matchPrior(Collection $prior, ?string $iban, ?string $name, ?string $currency): ?BankAccount
    {
        if ($iban !== null) {
            return $prior->first(fn (BankAccount $a) => $a->iban === $iban);
        }

        if ($name === null) {
            return null;
        }

        $candidates = $prior->filter(fn (BankAccount $a) => $a->iban === null && $a->name === $name && $a->currency === $currency);

        return $candidates->count() === 1 ? $candidates->first() : null;
    }
}
