<?php

declare(strict_types=1);

namespace App\Http\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only client for Enable Banking (open banking).
 *
 * Auth is a self-signed RS256 JWT (application id as `kid`, signed with the
 * application's PEM private key). Read flow: list ASPSPs (banks) -> start a
 * user authorization (consent URL) -> exchange the redirect code for a session
 * + accounts -> read an account balance. The client never initiates payments.
 *
 * For personal use this runs against an Enable Banking "restricted mode"
 * production app with the user's own accounts pre-linked in the EB portal.
 */
class EnableBankingClient
{
    private const BASE_URL = 'https://api.enablebanking.com';

    private const JWT_CACHE_KEY = 'enable_banking.jwt';

    public function __construct(
        private readonly string $applicationId,
        private readonly string $privateKeyPath,
    ) {}

    /**
     * List the connectable banks (ASPSPs) for a country.
     *
     * @return list<array<int|string, mixed>>
     */
    public function aspsps(string $country, string $psuType = 'personal'): array
    {
        $response = $this->request()?->get(self::BASE_URL.'/aspsps', [
            'country' => $country,
            'psu_type' => $psuType,
        ]);

        if ($response === null || ! $response->successful()) {
            Log::warning('Enable Banking aspsps fetch failed', ['status' => $response?->status()]);

            return [];
        }

        $aspsps = $response->json('aspsps');

        return is_array($aspsps) ? array_values(array_filter($aspsps, 'is_array')) : [];
    }

    /**
     * Start a user authorization and return the consent URL plus the
     * authorization id to correlate the redirect.
     *
     * @return array{url: string, authorization_id: string}|null
     */
    public function startAuthorization(string $aspspName, string $country, string $redirectUrl, string $state): ?array
    {
        $response = $this->request()?->post(self::BASE_URL.'/auth', [
            'access' => ['valid_until' => Carbon::now()->addDays(90)->toIso8601ZuluString()],
            'aspsp' => ['name' => $aspspName, 'country' => $country],
            'state' => $state,
            'redirect_url' => $redirectUrl,
            'psu_type' => 'personal',
        ]);

        if ($response === null || ! $response->successful()) {
            Log::warning('Enable Banking auth start failed', ['status' => $response?->status(), 'aspsp' => $aspspName]);

            return null;
        }

        $url = $response->json('url');
        $authorizationId = $response->json('authorization_id');

        if (! is_string($url) || ! is_string($authorizationId)) {
            return null;
        }

        return ['url' => $url, 'authorization_id' => $authorizationId];
    }

    /**
     * Exchange the redirect `code` for a session and its accounts. Each account
     * carries a `uid` used later to read balances. `valid_until` is the real
     * session validity reported by the bank (which may be shorter than the 90
     * days we requested), or null if the response omitted it.
     *
     * @return array{session_id: string, accounts: list<array<int|string, mixed>>, valid_until: Carbon|null}|null
     */
    public function authorizeSession(string $code): ?array
    {
        $response = $this->request()?->post(self::BASE_URL.'/sessions', ['code' => $code]);

        if ($response === null || ! $response->successful()) {
            Log::warning('Enable Banking session authorization failed', ['status' => $response?->status()]);

            return null;
        }

        $sessionId = $response->json('session_id');
        $accounts = $response->json('accounts');

        if (! is_string($sessionId) || ! is_array($accounts)) {
            return null;
        }

        $validUntilRaw = $response->json('access.valid_until');
        $validUntil = is_string($validUntilRaw) ? Carbon::parse($validUntilRaw) : null;

        return [
            'session_id' => $sessionId,
            'accounts' => array_values(array_filter($accounts, 'is_array')),
            'valid_until' => $validUntil,
        ];
    }

    /**
     * The current balance of an account (by its session uid). Returns the
     * balance on success, the string 'unauthorized' when the bank rejects the
     * session (401/403 — consent revoked or expired), or null for any other
     * failure (network, 5xx, unexpected shape). The caller distinguishes the
     * 'unauthorized' case to expire the connection; a transient error does not.
     *
     * @return array{amount: float, currency: string}|'unauthorized'|null
     */
    public function accountBalance(string $accountUid): array|string|null
    {
        $response = $this->request()?->get(self::BASE_URL.'/accounts/'.$accountUid.'/balances');

        if ($response !== null && in_array($response->status(), [401, 403], true)) {
            Log::warning('Enable Banking balance unauthorized', ['status' => $response->status(), 'account' => $accountUid]);

            return 'unauthorized';
        }

        if ($response === null || ! $response->successful()) {
            Log::warning('Enable Banking balance fetch failed', ['status' => $response?->status(), 'account' => $accountUid]);

            return null;
        }

        $amount = $response->json('balances.0.balance_amount.amount');
        $currency = $response->json('balances.0.balance_amount.currency');

        if (! is_numeric($amount) || ! is_string($currency)) {
            Log::warning('Enable Banking unexpected balance shape', ['account' => $accountUid]);

            return null;
        }

        return ['amount' => (float) $amount, 'currency' => $currency];
    }

    private function request(): ?PendingRequest
    {
        $jwt = $this->jwt();
        if ($jwt === null) {
            return null;
        }

        return Http::withToken($jwt)
            ->timeout(10)
            ->retry(3, 200, throw: false);
    }

    private function jwt(): ?string
    {
        // Unconfigured (no credentials) → fully inert: no cache, no HTTP.
        if ($this->applicationId === '' || $this->privateKeyPath === '') {
            return null;
        }

        /** @var string|null $cached */
        $cached = Cache::get(self::JWT_CACHE_KEY);
        if (is_string($cached)) {
            return $cached;
        }

        $key = $this->loadPrivateKey();
        if ($key === null) {
            return null;
        }

        $issuedAt = Carbon::now()->getTimestamp();
        $expiresAt = $issuedAt + 3600;

        $header = ['typ' => 'JWT', 'alg' => 'RS256', 'kid' => $this->applicationId];
        $claims = [
            'iss' => 'enablebanking.com',
            'aud' => 'api.enablebanking.com',
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ];

        $signingInput = $this->base64UrlEncode($this->jsonEncode($header))
            .'.'.$this->base64UrlEncode($this->jsonEncode($claims));

        $signature = '';
        if (openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256) === false) {
            Log::warning('Enable Banking JWT signing failed');

            return null;
        }

        $jwt = $signingInput.'.'.$this->base64UrlEncode($signature);

        // Cache under the token TTL so a fresh JWT is minted before expiry.
        Cache::put(self::JWT_CACHE_KEY, $jwt, $expiresAt - $issuedAt - 60);

        return $jwt;
    }

    private function loadPrivateKey(): ?\OpenSSLAsymmetricKey
    {
        if ($this->privateKeyPath === '' || ! is_file($this->privateKeyPath)) {
            Log::warning('Enable Banking private key missing', ['path' => $this->privateKeyPath]);

            return null;
        }

        $pem = file_get_contents($this->privateKeyPath);
        if ($pem === false) {
            return null;
        }

        $key = openssl_pkey_get_private($pem);

        return $key === false ? null : $key;
    }

    /** @param array<string, mixed> $value */
    private function jsonEncode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
