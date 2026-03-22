<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceFetcherService
{
    private const COINGECKO_URL = 'https://api.coingecko.com/api/v3/simple/price';

    private const YAHOO_FINANCE_URL = 'https://query1.finance.yahoo.com/v8/finance/chart/';

    private const CRYPTO_TICKERS = ['BTC'];

    private const BLOCKSTREAM_URL = 'https://blockstream.info/api/address/';

    public function fetchAll(): void
    {
        $this->fetchWalletBalances();

        $tickers = Asset::whereNotNull('ticker')
            ->distinct()
            ->pluck('ticker')
            ->all();

        if (empty($tickers)) {
            return;
        }

        $cryptoTickers = array_values(array_filter($tickers, fn (mixed $t) => is_string($t) && in_array(strtoupper($t), self::CRYPTO_TICKERS, true)));
        $etfTickers = array_values(array_filter($tickers, fn (mixed $t) => is_string($t) && ! in_array(strtoupper($t), self::CRYPTO_TICKERS, true)));

        if (! empty($cryptoTickers)) {
            $this->fetchCryptoPrices($cryptoTickers);
        }

        foreach ($etfTickers as $ticker) {
            $this->fetchEtfPrice($ticker);
        }
    }

    private function fetchWalletBalances(): void
    {
        $assets = Asset::whereNotNull('wallet_address')->get();

        foreach ($assets as $asset) {
            /** @var string $address */
            $address = $asset->wallet_address;

            $response = Http::get(self::BLOCKSTREAM_URL.urlencode($address));

            if (! $response->successful()) {
                Log::warning('Blockstream fetch failed', ['address' => $address, 'status' => $response->status()]);

                continue;
            }

            /** @var array{chain_stats: array{funded_txo_sum: int, spent_txo_sum: int}} $data */
            $data = $response->json();

            $satoshis = $data['chain_stats']['funded_txo_sum'] - $data['chain_stats']['spent_txo_sum'];
            $btc = $satoshis / 100_000_000;

            $asset->quantity = $btc;
            $asset->save();
        }
    }

    /** @param string[] $tickers */
    private function fetchCryptoPrices(array $tickers): void
    {
        $coinIds = array_map(fn (string $t) => strtolower($t) === 'btc' ? 'bitcoin' : strtolower($t), $tickers);

        $response = Http::get(self::COINGECKO_URL, [
            'ids' => implode(',', $coinIds),
            'vs_currencies' => 'eur',
        ]);

        if (! $response->successful()) {
            Log::warning('CoinGecko fetch failed', ['status' => $response->status()]);

            return;
        }

        /** @var array<string, array<string, float>> $data */
        $data = $response->json();

        foreach ($tickers as $ticker) {
            $coinId = strtolower($ticker) === 'btc' ? 'bitcoin' : strtolower($ticker);

            if (! isset($data[$coinId]['eur'])) {
                continue;
            }

            $this->upsertPrice($ticker, (float) $data[$coinId]['eur']);
        }
    }

    private function fetchEtfPrice(string $ticker): void
    {
        // Try the ticker as-is, then with .MI suffix for European ETFs
        $candidates = str_contains($ticker, '.') ? [$ticker] : [$ticker, $ticker.'.MI'];

        foreach ($candidates as $symbol) {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get(self::YAHOO_FINANCE_URL.urlencode($symbol), [
                    'interval' => '1d',
                    'range' => '1d',
                ]);

            if (! $response->successful()) {
                continue;
            }

            /** @var array{chart: array{result: array<int, array{meta: array{regularMarketPrice?: mixed}}>|null}}|null $data */
            $data = $response->json();

            $rawPrice = $data['chart']['result'][0]['meta']['regularMarketPrice'] ?? null;

            if (! is_numeric($rawPrice)) {
                continue;
            }

            $this->upsertPrice($ticker, (float) $rawPrice);

            return;
        }

        Log::warning('Yahoo Finance missing price', ['ticker' => $ticker]);
    }

    private function upsertPrice(string $ticker, float $price): void
    {
        AssetPrice::updateOrCreate(
            ['ticker' => $ticker],
            [
                'price' => $price,
                'currency' => 'EUR',
                'fetched_at' => Carbon::now(),
            ]
        );
    }
}
