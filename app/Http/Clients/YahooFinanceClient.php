<?php

declare(strict_types=1);

namespace App\Http\Clients;

use Illuminate\Support\Facades\Http;

class YahooFinanceClient
{
    private const URL = 'https://query1.finance.yahoo.com/v8/finance/chart/';

    public function getPrice(string $symbol): ?float
    {
        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->timeout(10)
            ->retry(3, 200, throw: false)
            ->get(self::URL.urlencode($symbol), [
                'interval' => '1d',
                'range' => '1d',
            ]);

        if (! $response->successful()) {
            return null;
        }

        /** @var array{chart: array{result: array<int, array{meta: array{regularMarketPrice?: mixed}}>|null}}|null $data */
        $data = $response->json();

        $rawPrice = $data['chart']['result'][0]['meta']['regularMarketPrice'] ?? null;

        if (! is_numeric($rawPrice)) {
            return null;
        }

        return (float) $rawPrice;
    }
}
