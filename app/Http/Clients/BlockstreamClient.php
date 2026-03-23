<?php

declare(strict_types=1);

namespace App\Http\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlockstreamClient
{
    private const URL = 'https://blockstream.info/api/address/';

    private const SATOSHIS_PER_BTC = 100_000_000;

    public function getBalanceBtc(string $address): ?float
    {
        $response = Http::get(self::URL.urlencode($address));

        if (! $response->successful()) {
            Log::warning('Blockstream fetch failed', ['address' => $address, 'status' => $response->status()]);

            return null;
        }

        /** @var array{chain_stats: array{funded_txo_sum: int, spent_txo_sum: int}} $data */
        $data = $response->json();

        $satoshis = $data['chain_stats']['funded_txo_sum'] - $data['chain_stats']['spent_txo_sum'];

        return $satoshis / self::SATOSHIS_PER_BTC;
    }
}
