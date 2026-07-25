<?php

declare(strict_types=1);

namespace App\Http\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlockstreamClient
{
    private const string URL = 'https://blockstream.info/api/address/';

    private const int SATOSHIS_PER_BTC = 100_000_000;

    public function getBalanceBtc(string $address): ?float
    {
        $response = Http::timeout(10)
            ->retry(3, 200, throw: false)
            ->get(self::URL.urlencode($address));

        if (! $response->successful()) {
            Log::warning('Blockstream fetch failed', ['address' => $address, 'status' => $response->status()]);

            return null;
        }

        $funded = $response->json('chain_stats.funded_txo_sum');
        $spent = $response->json('chain_stats.spent_txo_sum');

        if (! is_numeric($funded) || ! is_numeric($spent)) {
            Log::warning('Blockstream unexpected response', ['address' => $address]);

            return null;
        }

        $satoshis = (int) $funded - (int) $spent;

        return $satoshis / self::SATOSHIS_PER_BTC;
    }
}
