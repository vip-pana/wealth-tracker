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

    public function test_yahoo_expense_ratio_reads_the_funds_ter_as_percent(): void
    {
        Http::fake([
            'fc.yahoo.com' => Http::response('', 200, ['Set-Cookie' => 'A1=token; Domain=.yahoo.com']),
            '*/v1/test/getcrumb' => Http::response('abc123', 200),
            '*/v10/finance/quoteSummary/*' => Http::response([
                'quoteSummary' => ['result' => [[
                    'fundProfile' => ['feesExpensesInvestment' => [
                        'annualReportExpenseRatio' => ['raw' => 0.002, 'fmt' => '0.20%'],
                    ]],
                ]]],
            ], 200),
        ]);

        // 0.002 fraction → 0.20 percent.
        $this->assertEqualsWithDelta(0.20, app(YahooFinanceClient::class)->getExpenseRatio('ISAC.MI'), 0.0001);
    }

    public function test_yahoo_expense_ratio_is_null_when_the_crumb_handshake_fails(): void
    {
        // No cookie issued → no crumb → graceful null, never throws.
        Http::fake([
            'fc.yahoo.com' => Http::response('', 500),
            '*/v1/test/getcrumb' => Http::response('', 500),
        ]);

        $this->assertNull(app(YahooFinanceClient::class)->getExpenseRatio('ISAC.MI'));
    }

    public function test_yahoo_expense_ratio_is_null_when_the_fund_lacks_a_ter(): void
    {
        Http::fake([
            'fc.yahoo.com' => Http::response('', 200, ['Set-Cookie' => 'A1=token; Domain=.yahoo.com']),
            '*/v1/test/getcrumb' => Http::response('abc123', 200),
            '*/v10/finance/quoteSummary/*' => Http::response([
                'quoteSummary' => ['result' => [['fundProfile' => ['feesExpensesInvestment' => []]]]],
            ], 200),
        ]);

        $this->assertNull(app(YahooFinanceClient::class)->getExpenseRatio('BTC'));
    }
}
