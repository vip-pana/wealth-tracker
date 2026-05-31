<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Http\Clients\BlockstreamClient;
use App\Http\Clients\CoinGeckoClient;
use App\Http\Clients\YahooFinanceClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PriceClientRobustnessTest extends TestCase
{
    public function test_yahoo_retries_a_transient_failure_then_succeeds(): void
    {
        Http::fakeSequence('query1.finance.yahoo.com/*')
            ->push('', 500)
            ->push(['chart' => ['result' => [['meta' => ['regularMarketPrice' => 104.42]]]]], 200);

        $price = app(YahooFinanceClient::class)->getPrice('ISAC');

        $this->assertEqualsWithDelta(104.42, $price, 0.001);
    }

    public function test_yahoo_returns_null_after_exhausting_retries_without_throwing(): void
    {
        Http::fake(['query1.finance.yahoo.com/*' => Http::response('', 503)]);

        $price = app(YahooFinanceClient::class)->getPrice('ISAC');

        $this->assertNull($price);
        // retry(3) = 3 total attempts (1 initial + 2 retries).
        Http::assertSentCount(3);
    }

    public function test_coingecko_returns_empty_after_exhausting_retries(): void
    {
        Http::fake(['api.coingecko.com/*' => Http::response('', 429)]);

        $data = app(CoinGeckoClient::class)->getPricesInEur(['bitcoin']);

        $this->assertSame([], $data);
    }

    public function test_blockstream_retries_then_succeeds(): void
    {
        Http::fakeSequence('blockstream.info/*')
            ->push('', 500)
            ->push(['chain_stats' => ['funded_txo_sum' => 150000000, 'spent_txo_sum' => 50000000]], 200);

        $btc = app(BlockstreamClient::class)->getBalanceBtc('bc1qexample');

        // (150_000_000 - 50_000_000) / 100_000_000 = 1.0 BTC
        $this->assertEqualsWithDelta(1.0, $btc, 0.0000001);
    }

    public function test_blockstream_guards_unexpected_json_instead_of_crashing(): void
    {
        // 200 OK but the expected chain_stats keys are missing.
        Http::fake(['blockstream.info/*' => Http::response(['unexpected' => true], 200)]);

        $btc = app(BlockstreamClient::class)->getBalanceBtc('bc1qexample');

        $this->assertNull($btc);
    }

    public function test_blockstream_returns_null_after_exhausting_retries(): void
    {
        Http::fake(['blockstream.info/*' => Http::response('', 502)]);

        $btc = app(BlockstreamClient::class)->getBalanceBtc('bc1qexample');

        $this->assertNull($btc);
    }
}
