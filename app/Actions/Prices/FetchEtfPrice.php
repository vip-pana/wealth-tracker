<?php

declare(strict_types=1);

namespace App\Actions\Prices;

use App\Actions\Action;
use App\Http\Clients\YahooFinanceClient;
use App\Models\Asset;
use App\Models\AssetPrice;
use Illuminate\Support\Facades\Log;

class FetchEtfPrice extends Action
{
    private const string CURRENCY = 'EUR';

    private const string MILAN_EXCHANGE_SUFFIX = '.MI';

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

            AssetPrice::recordSuccess($ticker, $price, self::CURRENCY);
            $this->backfillExpenseRatio($ticker, $symbol);

            return new PriceRefreshResult(updated: [$ticker]);
        }

        Log::warning('Yahoo Finance missing price', ['ticker' => $ticker]);
        AssetPrice::recordFailure($ticker, 'Prezzo non disponibile da Yahoo Finance.');

        return new PriceRefreshResult(failed: [$ticker]);
    }

    /**
     * Best-effort: stamp the fund's TER on this ticker's assets that don't have
     * one yet. The TER is quasi-static, so we only hit Yahoo's gated funds
     * endpoint when at least one asset is missing it — never re-fetching once
     * set (clear the field to force a refresh). A null result (Yahoo doesn't
     * carry it, or the crumb handshake failed) is silently ignored; this must
     * never affect the price refresh.
     */
    private function backfillExpenseRatio(string $ticker, string $symbol): void
    {
        $missing = Asset::query()
            ->where('ticker', $ticker)
            ->whereNull('expense_ratio')
            ->exists();

        if (! $missing) {
            return;
        }

        $ter = $this->yahooFinance->getExpenseRatio($symbol);

        if ($ter === null) {
            return;
        }

        Asset::query()
            ->where('ticker', $ticker)
            ->whereNull('expense_ratio')
            ->update(['expense_ratio' => $ter]);
    }
}
