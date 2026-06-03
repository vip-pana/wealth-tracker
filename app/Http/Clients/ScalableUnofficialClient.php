<?php

declare(strict_types=1);

namespace App\Http\Clients;

use Illuminate\Http\Client\PendingRequest;
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
        $response = $this->request()?->get($this->url('/portfolio/inventory'));

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
        $response = $this->request()?->get($this->url('/portfolio/cash'));

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

    private function request(): ?PendingRequest
    {
        if ($this->balanceUrl === '' || $this->token === '') {
            return null;
        }

        return Http::withHeaders(['X-Gateway-Token' => $this->token])
            ->timeout(15)
            ->retry(2, 200, throw: false);
    }

    private function url(string $path): string
    {
        return rtrim($this->balanceUrl, '/').$path;
    }
}
