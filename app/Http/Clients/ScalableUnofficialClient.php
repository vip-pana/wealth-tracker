<?php

declare(strict_types=1);

namespace App\Http\Clients;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only client for the local unofficial Scalable Capital API proxy.
 *
 * Stopgap until the official Scalable CLI is allowlisted: the proxy runs on the
 * host (browser+2FA login, 8h session) and exposes portfolio data behind an
 * X-Gateway-Token header. This client only reads positions and the cash
 * balance; it never logs in or places orders.
 */
class ScalableUnofficialClient
{
    public function __construct(
        private readonly string $balanceUrl,
        private readonly string $token,
    ) {}

    /**
     * Current positions, each with its live market value (filled quantity times
     * mid price). Returns null on any failure (unconfigured, proxy down, session
     * expired, unexpected shape) so the caller leaves stored values untouched.
     *
     * @return list<array{isin: string, name: string, value: float}>|null
     */
    public function positions(): ?array
    {
        $response = $this->get('/portfolio/inventory');

        if ($response === null || ! $response->successful()) {
            Log::warning('Scalable unofficial inventory fetch failed', ['status' => $response?->status()]);

            return null;
        }

        $items = $response->json('account.brokerPortfolio.inventory.ungroupedInventoryItems.items');

        if (! is_array($items)) {
            Log::warning('Scalable unofficial unexpected inventory shape');

            return null;
        }

        $positions = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $isin = $item['isin'] ?? null;
            $name = $item['name'] ?? null;
            $inventory = $item['inventory'] ?? null;
            $position = is_array($inventory) ? ($inventory['position'] ?? null) : null;
            $quantity = is_array($position) ? ($position['filled'] ?? null) : null;
            $quoteTick = $item['quoteTick'] ?? null;
            $midPrice = is_array($quoteTick) ? ($quoteTick['midPrice'] ?? null) : null;

            if (! is_string($isin) || ! is_string($name) || ! is_numeric($quantity) || ! is_numeric($midPrice)) {
                Log::warning('Scalable unofficial unexpected position shape');

                continue;
            }

            $positions[] = [
                'isin' => $isin,
                'name' => $name,
                'value' => (float) $quantity * (float) $midPrice,
            ];
        }

        return $positions;
    }

    /**
     * The uninvested cash balance in EUR, or null on any failure.
     */
    public function cashBalance(): ?float
    {
        $response = $this->get('/portfolio/cash');

        if ($response === null || ! $response->successful()) {
            Log::warning('Scalable unofficial cash fetch failed', ['status' => $response?->status()]);

            return null;
        }

        $cash = $response->json('account.brokerPortfolio.payments.buyingPower.cashBalance');

        if (! is_numeric($cash)) {
            Log::warning('Scalable unofficial unexpected cash shape');

            return null;
        }

        return (float) $cash;
    }

    /**
     * Start an interactive login on the proxy: it opens a browser on the host
     * for the user to complete Scalable's login + 2FA, then captures the
     * session. Blocks until the user finishes (the proxy waits up to ~120s), or
     * returns immediately if a valid session already exists. Returns true on a
     * live session, false if the proxy is unreachable, times out, or rejects.
     *
     * The user's credentials never touch this app — they are typed into the
     * browser on the host. The proxy exempts /auth from the gateway token.
     */
    public function login(): bool
    {
        if ($this->balanceUrl === '') {
            return false;
        }

        try {
            $response = Http::withHeaders(['X-Gateway-Token' => $this->token])
                ->timeout(130)
                ->post(rtrim($this->balanceUrl, '/').'/auth/login');
        } catch (ConnectionException $e) {
            Log::warning('Scalable unofficial login proxy unreachable', ['message' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Scalable unofficial login failed', ['status' => $response->status()]);

            return false;
        }

        return true;
    }

    /**
     * GET a proxy path, or null if unconfigured or the proxy is unreachable.
     * A down proxy (the common case when the host server isn't running) is a
     * connection error, not an HTTP status — treat it the same as any other
     * failure so the sync degrades instead of throwing.
     */
    private function get(string $path): ?Response
    {
        $request = $this->request();
        if ($request === null) {
            return null;
        }

        try {
            return $request->get(rtrim($this->balanceUrl, '/').$path);
        } catch (ConnectionException $e) {
            Log::warning('Scalable unofficial proxy unreachable', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function request(): ?PendingRequest
    {
        if ($this->balanceUrl === '' || $this->token === '') {
            return null;
        }

        return Http::withHeaders(['X-Gateway-Token' => $this->token])
            ->timeout(15)
            ->retry(2, 200, throw: false);
    }
}
