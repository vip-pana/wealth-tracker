<?php

declare(strict_types=1);

namespace App\Actions\Prices;

use App\Actions\Action;
use App\Http\Clients\YahooFinanceClient;
use App\Models\AssetPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FetchEtfPrice extends Action
{
    private const CURRENCY = 'EUR';

    private const MILAN_EXCHANGE_SUFFIX = '.MI';

    public function __construct(
        private readonly YahooFinanceClient $yahooFinance,
    ) {}

    public function run(string $ticker): PriceRefreshResult
    {
        $candidates = str_contains($ticker, '.') ? [$ticker] : [$ticker, $ticker.self::MILAN_EXCHANGE_SUFFIX];

        foreach ($candidates as $symbol) {
            $price = $this->yahooFinance->getPrice($symbol);

            if ($price === null) {
                continue;
            }

            AssetPrice::updateOrCreate(
                ['ticker' => $ticker],
                [
                    'price' => $price,
                    'currency' => self::CURRENCY,
                    'fetched_at' => Carbon::now(),
                ]
            );

            return new PriceRefreshResult(updated: [$ticker]);
        }

        Log::warning('Yahoo Finance missing price', ['ticker' => $ticker]);

        return new PriceRefreshResult(failed: [$ticker]);
    }
}
