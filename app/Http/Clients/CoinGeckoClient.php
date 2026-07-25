<?php

declare(strict_types=1);

namespace App\Http\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoinGeckoClient
{
    private const string URL = 'https://api.coingecko.com/api/v3/simple/price';

    /**
     * @param  string[]  $coinIds
     * @return array<string, array<string, float>>
     */
    public function getPricesInEur(array $coinIds): array
    {
        $response = Http::timeout(10)
            ->retry(3, 200, throw: false)
            ->get(self::URL, [
                'ids' => implode(',', $coinIds),
                'vs_currencies' => 'eur',
            ]);

        if (! $response->successful()) {
            Log::warning('CoinGecko fetch failed', ['status' => $response->status()]);

            return [];
        }

        /** @var array<string, array<string, float>> $data */
        $data = $response->json();

        return $data;
    }
}
