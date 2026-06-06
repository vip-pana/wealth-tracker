<?php

declare(strict_types=1);

namespace App\Actions\Prices;

use App\Actions\Action;
use App\Models\Asset;

class FetchAllPrices extends Action
{
    private const CRYPTO_TICKERS = ['BTC'];

    public function __construct(
        private readonly FetchWalletBalances $fetchWalletBalances,
        private readonly FetchBankBalances $fetchBankBalances,
        private readonly FetchScalableBalance $fetchScalableBalance,
        private readonly FetchCryptoPrices $fetchCryptoPrices,
        private readonly FetchEtfPrice $fetchEtfPrice,
    ) {}

    public function run(): PriceRefreshResult
    {
        $result = $this->fetchWalletBalances->run()
            ->merge($this->fetchBankBalances->run())
            ->merge($this->fetchScalableBalance->run());

        $tickers = Asset::whereNotNull('ticker')
            ->distinct()
            ->pluck('ticker')
            ->all();

        if (empty($tickers)) {
            return $result;
        }

        $cryptoTickers = array_values(array_filter($tickers, fn (mixed $t) => is_string($t) && in_array(strtoupper($t), self::CRYPTO_TICKERS, true)));
        $etfTickers = array_values(array_filter($tickers, fn (mixed $t) => is_string($t) && ! in_array(strtoupper($t), self::CRYPTO_TICKERS, true)));

        if (! empty($cryptoTickers)) {
            $result = $result->merge($this->fetchCryptoPrices->run($cryptoTickers));
        }

        foreach ($etfTickers as $ticker) {
            $result = $result->merge($this->fetchEtfPrice->run($ticker));
        }

        return $result;
    }
}
