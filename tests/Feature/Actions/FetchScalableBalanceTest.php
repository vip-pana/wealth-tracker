<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Prices\FetchScalableBalance;
use App\Models\Asset;
use App\Models\Category;
use App\Models\ScalableConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class FetchScalableBalanceTest extends TestCase
{
    use RefreshDatabase;

    private int $stocksId;

    private int $cashId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stocksId = Category::factory()->create()->id;
        $this->cashId = Category::factory()->create()->id;

        config([
            'services.scalable.balance_url' => 'http://scalable.test',
            'services.scalable.token' => 'tok',
            'services.scalable.cash_category_id' => $this->cashId,
            'services.scalable.cash_asset_name' => 'Scalable Liquidità',
        ]);
    }

    /**
     * @param  list<array{isin: string, name: string, qty: float, mid: float}>  $positions
     */
    private function fakeProxy(array $positions, float $cash): void
    {
        $items = array_map(fn (array $p): array => [
            'isin' => $p['isin'],
            'name' => $p['name'],
            'inventory' => ['position' => ['filled' => $p['qty']]],
            'quoteTick' => ['midPrice' => $p['mid']],
        ], $positions);

        Http::fake([
            'scalable.test/portfolio/inventory' => Http::response([
                'account' => ['brokerPortfolio' => ['inventory' => ['ungroupedInventoryItems' => ['items' => $items]]]],
            ]),
            'scalable.test/portfolio/cash' => Http::response([
                'account' => ['brokerPortfolio' => ['payments' => ['buyingPower' => ['cashBalance' => $cash]]]],
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function cliEnvelope(array $data): string
    {
        return (string) json_encode(['ok' => true, 'data' => $data]);
    }

    /**
     * @param  list<array{isin: string, name: string, value: float}>  $holdings
     */
    private function fakeCli(array $holdings, float $total, bool $loggedIn = true): void
    {
        $securities = array_sum(array_column($holdings, 'value'));

        Process::fake([
            '*whoami*' => $loggedIn
                ? Process::result($this->cliEnvelope(['result' => ['personOverview' => ['id' => 'x']]]))
                : Process::result((string) json_encode(['ok' => false, 'error' => ['code' => 'no_session']])),
            '*broker*holdings*' => Process::result($this->cliEnvelope([
                'result' => ['items' => array_map(fn (array $h): array => [
                    'isin' => $h['isin'], 'name' => $h['name'], 'valuation' => $h['value'], 'valuation_currency' => 'EUR',
                ], $holdings)],
            ])),
            '*broker*overview*' => Process::result($this->cliEnvelope([
                'result' => ['valuation' => ['total' => $total, 'securities' => $securities, 'crypto' => 0]],
            ])),
        ]);
    }

    public function test_updates_the_asset_carrying_the_isin_at_market_value(): void
    {
        $acwi = Asset::factory()->create(['category_id' => $this->stocksId, 'name' => 'ACWI', 'isin' => 'IE00B6R52259', 'value' => 1, 'date' => now()->format('Y-m-01')]);
        $cash = Asset::factory()->create(['category_id' => $this->cashId, 'name' => 'Scalable Liquidità', 'value' => 1, 'date' => now()->format('Y-m-01')]);
        $this->fakeProxy([
            ['isin' => 'IE00B6R52259', 'name' => 'iShares MSCI ACWI (Acc)', 'qty' => 10.0, 'mid' => 100.0],
        ], 500.0);

        $result = app(FetchScalableBalance::class)->run();

        $this->assertSame([], $result->failed);
        $acwi->refresh();
        $cash->refresh();
        $this->assertEqualsWithDelta(1000.0, (float) $acwi->value, 0.001);
        $this->assertEqualsWithDelta(500.0, (float) $cash->value, 0.001);
        $this->assertNotNull($acwi->bank_synced_at);
        // The asset keeps its own display name; the API name is ignored.
        $this->assertSame('ACWI', $acwi->name);
        // A successful sync records healthy connection state.
        $this->assertSame(ScalableConnection::SYNC_OK, ScalableConnection::current()->last_sync_status);
    }

    public function test_skips_a_position_no_asset_carries(): void
    {
        $this->fakeProxy([
            ['isin' => 'UNTRACKED', 'name' => 'Mystery', 'qty' => 5.0, 'mid' => 10.0],
        ], 0.0);

        $result = app(FetchScalableBalance::class)->run();

        $this->assertNotContains('Mystery', $result->updated);
        $this->assertDatabaseMissing('assets', ['name' => 'Mystery']);
    }

    public function test_follows_the_asset_into_a_new_month(): void
    {
        // Last month's row carries the ISIN; the current month has none yet.
        Asset::factory()->create([
            'category_id' => $this->stocksId, 'name' => 'ACWI', 'isin' => 'IE00B6R52259', 'value' => 500,
            'date' => now()->subMonthNoOverflow()->format('Y-m-01'),
        ]);
        $this->fakeProxy([
            ['isin' => 'IE00B6R52259', 'name' => 'ACWI', 'qty' => 10.0, 'mid' => 65.0],
        ], 0.0);

        app(FetchScalableBalance::class)->run();

        // A fresh current-month row carries the new value and the ISIN.
        $this->assertDatabaseHas('assets', [
            'name' => 'ACWI', 'date' => now()->format('Y-m-01'), 'value' => 650.0, 'isin' => 'IE00B6R52259',
        ]);
        $this->assertSame(2, Asset::where('isin', 'IE00B6R52259')->count());
    }

    public function test_failure_preserves_existing_values(): void
    {
        $acwi = Asset::factory()->create(['category_id' => $this->stocksId, 'name' => 'ACWI', 'isin' => 'IE00B6R52259', 'value' => 999, 'date' => now()->format('Y-m-01')]);
        Http::fake([
            'scalable.test/portfolio/inventory' => Http::response('', 500),
            'scalable.test/portfolio/cash' => Http::response('', 500),
        ]);

        $result = app(FetchScalableBalance::class)->run();

        $this->assertContains('Scalable', $result->failed);
        $acwi->refresh();
        $this->assertEqualsWithDelta(999.0, (float) $acwi->value, 0.001);
        $this->assertNull($acwi->bank_synced_at);
        // A failed sync is recorded so Settings can surface it after the toast.
        $connection = ScalableConnection::current();
        $this->assertSame(ScalableConnection::SYNC_FAILED, $connection->last_sync_status);
        $this->assertNotNull($connection->last_sync_error);
    }

    public function test_inert_when_unconfigured(): void
    {
        config(['services.scalable.balance_url' => '']);
        Http::fake();
        Process::fake();

        $result = app(FetchScalableBalance::class)->run();

        $this->assertSame([], $result->updated);
        $this->assertSame([], $result->failed);
        Http::assertNothingSent();
        Process::assertNothingRan();
    }

    public function test_auto_prefers_the_cli_when_logged_in(): void
    {
        config(['services.scalable.cli.enabled' => true]);
        $acwi = Asset::factory()->create(['category_id' => $this->stocksId, 'name' => 'ACWI', 'isin' => 'IE00B6R52259', 'value' => 1, 'date' => now()->format('Y-m-01')]);
        $cash = Asset::factory()->create(['category_id' => $this->cashId, 'name' => 'Scalable Liquidità', 'value' => 1, 'date' => now()->format('Y-m-01')]);
        Http::fake();
        $this->fakeCli([
            ['isin' => 'IE00B6R52259', 'name' => 'iShares MSCI ACWI (Acc)', 'value' => 15497.4],
        ], total: 19075.4);

        $result = app(FetchScalableBalance::class)->run();

        $this->assertSame([], $result->failed);
        $acwi->refresh();
        $cash->refresh();
        $this->assertEqualsWithDelta(15497.4, (float) $acwi->value, 0.001);
        $this->assertEqualsWithDelta(3578.0, (float) $cash->value, 0.001);
        $this->assertSame(ScalableConnection::SYNC_OK, ScalableConnection::current()->last_sync_status);
        // The CLI is the source, so the proxy is never contacted.
        Http::assertNothingSent();
    }

    public function test_auto_falls_back_to_the_proxy_when_the_cli_session_lapsed(): void
    {
        config(['services.scalable.cli.enabled' => true]);
        $acwi = Asset::factory()->create(['category_id' => $this->stocksId, 'name' => 'ACWI', 'isin' => 'IE00B6R52259', 'value' => 1, 'date' => now()->format('Y-m-01')]);
        // CLI reports no session; the proxy is reachable and supplies the data.
        $this->fakeCli([], total: 0, loggedIn: false);
        $this->fakeProxy([
            ['isin' => 'IE00B6R52259', 'name' => 'ACWI', 'qty' => 10.0, 'mid' => 100.0],
        ], 0.0);

        $result = app(FetchScalableBalance::class)->run();

        $this->assertSame([], $result->failed);
        $acwi->refresh();
        $this->assertEqualsWithDelta(1000.0, (float) $acwi->value, 0.001);
        // Only whoami ran on the CLI; the portfolio came from the proxy.
        Process::assertNotRan(fn ($process): bool => str_contains(
            is_array($process->command) ? implode(' ', $process->command) : (string) $process->command,
            'broker',
        ));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'portfolio/inventory'));
    }

    public function test_source_cli_pinned_fails_when_session_lapsed(): void
    {
        config(['services.scalable.cli.enabled' => true, 'services.scalable.source' => 'cli']);
        $acwi = Asset::factory()->create(['category_id' => $this->stocksId, 'name' => 'ACWI', 'isin' => 'IE00B6R52259', 'value' => 999, 'date' => now()->format('Y-m-01')]);
        $this->fakeCli([], total: 0, loggedIn: false);
        Http::fake();

        $result = app(FetchScalableBalance::class)->run();

        $this->assertContains('Scalable', $result->failed);
        $acwi->refresh();
        $this->assertEqualsWithDelta(999.0, (float) $acwi->value, 0.001);
        $connection = ScalableConnection::current();
        $this->assertSame(ScalableConnection::SYNC_FAILED, $connection->last_sync_status);
        // Pinned to the CLI: the proxy is never tried.
        Http::assertNothingSent();
    }
}
