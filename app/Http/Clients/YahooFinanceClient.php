<?php

declare(strict_types=1);

namespace App\Http\Clients;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class YahooFinanceClient
{
    private const URL = 'https://query1.finance.yahoo.com/v8/finance/chart/';

    private const SUMMARY_URL = 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/';

    private const CRUMB_URL = 'https://query1.finance.yahoo.com/v1/test/getcrumb';

    private const COOKIE_URL = 'https://fc.yahoo.com';

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

    /**
     * The fund's annual expense ratio (TER) as a percentage, e.g. 0.20 for a
     * 0.20%/yr fund, or null if Yahoo doesn't carry it for this symbol.
     *
     * This reads the quoteSummary `fundProfile` module, which (unlike the price
     * chart) requires a session cookie + crumb. The handshake is best-effort:
     * any failure returns null so a missing TER never disturbs the price fetch.
     */
    public function getExpenseRatio(string $symbol): ?float
    {
        $crumb = $this->crumb();
        if ($crumb === null) {
            return null;
        }

        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->withCookies($this->cookieJar(), 'finance.yahoo.com')
            ->timeout(10)
            ->retry(2, 200, throw: false)
            ->get(self::SUMMARY_URL.urlencode($symbol), [
                'modules' => 'fundProfile',
                'crumb' => $crumb,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $raw = $response->json('quoteSummary.result.0.fundProfile.feesExpensesInvestment.annualReportExpenseRatio.raw');

        if (! is_numeric($raw)) {
            return null;
        }

        // Yahoo reports the ratio as a fraction (0.002 = 0.20%); store as percent.
        return round((float) $raw * 100, 4);
    }

    /**
     * A Yahoo session cookie + crumb, cached for the run. Yahoo gates the
     * fundamentals endpoints behind a per-session crumb; we fetch a cookie
     * from fc.yahoo.com, then exchange it for a crumb. Cached briefly so a
     * batch of tickers in one refresh shares a single handshake.
     *
     * @return array<string, string>
     */
    private function cookieJar(): array
    {
        /** @var array<string, string> $jar */
        $jar = Cache::get('yahoo.cookie', []);

        return $jar;
    }

    private function crumb(): ?string
    {
        /** @var string|null $cached */
        $cached = Cache::get('yahoo.crumb');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $cookieResponse = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->timeout(10)
            ->get(self::COOKIE_URL);

        $cookies = [];
        foreach ($cookieResponse->cookies()->toArray() as $cookie) {
            if (isset($cookie['Name'], $cookie['Value'])) {
                $cookies[$cookie['Name']] = $cookie['Value'];
            }
        }

        if ($cookies === []) {
            return null;
        }

        $crumbResponse = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->withCookies($cookies, 'finance.yahoo.com')
            ->timeout(10)
            ->get(self::CRUMB_URL);

        $crumb = trim($crumbResponse->body());

        if (! $crumbResponse->successful() || $crumb === '' || str_contains($crumb, '{')) {
            return null;
        }

        Cache::put('yahoo.cookie', $cookies, now()->addMinutes(30));
        Cache::put('yahoo.crumb', $crumb, now()->addMinutes(30));

        return $crumb;
    }
}
