<?php

declare(strict_types=1);

namespace App\Actions\Prices;

use App\Actions\Action;
use App\Http\Clients\CoinGeckoClient;
use App\Models\AssetPrice;
use Illuminate\Support\Facades\Log;

class FetchCryptoPrices extends Action
{
    private const CURRENCY = 'EUR';

    private const BTC_COINGECKO_ID = 'bitcoin';

    public function __construct(
        private readonly CoinGeckoClient $coinGecko,
    ) {}

    /** @param string[] $tickers */
    public function run(array $tickers): PriceRefreshResult
    {
        $coinIds = array_map(fn (string $t) => strtolower($t) === 'btc' ? self::BTC_COINGECKO_ID : strtolower($t), $tickers);

        $data = $this->coinGecko->getPricesInEur($coinIds);

        $updated = [];
        $failed = [];

        foreach ($tickers as $ticker) {
            $coinId = strtolower($ticker) === 'btc' ? self::BTC_COINGECKO_ID : strtolower($ticker);

            if (! isset($data[$coinId]['eur'])) {
                Log::warning('CoinGecko missing price', ['ticker' => $ticker]);
                AssetPrice::recordFailure($ticker, 'Prezzo non disponibile da CoinGecko.');
                $failed[] = $ticker;

                continue;
            }

            AssetPrice::recordSuccess($ticker, (float) $data[$coinId]['eur'], self::CURRENCY);
            $updated[] = $ticker;
        }

        return new PriceRefreshResult($updated, $failed);
    }
}
