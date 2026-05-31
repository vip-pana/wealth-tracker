<?php

declare(strict_types=1);

namespace App\Http\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only client for GoCardless Bank Account Data (open banking).
 *
 * Flow: secrets -> access token (cached) -> institutions -> agreement +
 * requisition (consent link) -> accounts -> balances. The client never
 * writes to the bank; it only reads balances and account metadata.
 */
class GoCardlessClient
{
    private const BASE_URL = 'https://bankaccountdata.gocardless.com/api/v2';

    private const TOKEN_CACHE_KEY = 'gocardless.access_token';

    public function __construct(
        private readonly string $secretId,
        private readonly string $secretKey,
    ) {}

    /**
     * List the connectable institutions (banks) for a two-letter country code.
     *
     * @return list<array{id: string, name: string, bic?: string, logo?: string}>
     */
    public function institutions(string $country): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return [];
        }

        $response = $this->request($token)->get(self::BASE_URL.'/institutions/', ['country' => $country]);

        if (! $response->successful()) {
            Log::warning('GoCardless institutions fetch failed', ['status' => $response->status()]);

            return [];
        }

        /** @var list<array{id: string, name: string, bic?: string, logo?: string}> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    /**
     * Start a consent flow for an institution. Returns the requisition id and
     * the consent link the user must open to authenticate with their bank.
     *
     * @return array{requisition_id: string, link: string}|null
     */
    public function createRequisition(string $institutionId, string $redirect): ?array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return null;
        }

        $response = $this->request($token)->post(self::BASE_URL.'/requisitions/', [
            'redirect' => $redirect,
            'institution_id' => $institutionId,
            'user_language' => 'IT',
        ]);

        if (! $response->successful()) {
            Log::warning('GoCardless requisition failed', ['status' => $response->status(), 'institution' => $institutionId]);

            return null;
        }

        $id = $response->json('id');
        $link = $response->json('link');

        if (! is_string($id) || ! is_string($link)) {
            return null;
        }

        return ['requisition_id' => $id, 'link' => $link];
    }

    /**
     * The account ids linked to a requisition once the user has consented.
     *
     * @return list<string>
     */
    public function requisitionAccounts(string $requisitionId): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return [];
        }

        $response = $this->request($token)->get(self::BASE_URL.'/requisitions/'.$requisitionId.'/');

        if (! $response->successful()) {
            Log::warning('GoCardless requisition lookup failed', ['status' => $response->status()]);

            return [];
        }

        $accounts = $response->json('accounts');

        if (! is_array($accounts)) {
            return [];
        }

        return array_values(array_filter($accounts, 'is_string'));
    }

    /**
     * The current balance of an account, or null if unavailable.
     *
     * @return array{amount: float, currency: string}|null
     */
    public function accountBalance(string $accountId): ?array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return null;
        }

        $response = $this->request($token)->get(self::BASE_URL.'/accounts/'.$accountId.'/balances/');

        if (! $response->successful()) {
            Log::warning('GoCardless balance fetch failed', ['status' => $response->status(), 'account' => $accountId]);

            return null;
        }

        $first = $response->json('balances.0.balanceAmount');

        if (! is_array($first)) {
            Log::warning('GoCardless unexpected balance shape', ['account' => $accountId]);

            return null;
        }

        $amount = $first['amount'] ?? null;
        $currency = $first['currency'] ?? null;

        if (! is_numeric($amount) || ! is_string($currency)) {
            Log::warning('GoCardless unexpected balance shape', ['account' => $accountId]);

            return null;
        }

        return ['amount' => (float) $amount, 'currency' => $currency];
    }

    private function accessToken(): ?string
    {
        /** @var string|null $cached */
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached)) {
            return $cached;
        }

        $response = Http::timeout(10)
            ->retry(3, 200, throw: false)
            ->post(self::BASE_URL.'/token/new/', [
                'secret_id' => $this->secretId,
                'secret_key' => $this->secretKey,
            ]);

        if (! $response->successful()) {
            Log::warning('GoCardless token request failed', ['status' => $response->status()]);

            return null;
        }

        $access = $response->json('access');
        $expires = $response->json('access_expires');

        if (! is_string($access)) {
            return null;
        }

        // Cache slightly under the real expiry to avoid using a token mid-expiry.
        $ttl = is_numeric($expires) ? max(60, (int) $expires - 60) : 3600;
        Cache::put(self::TOKEN_CACHE_KEY, $access, $ttl);

        return $access;
    }

    private function request(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->timeout(10)
            ->retry(3, 200, throw: false);
    }
}
