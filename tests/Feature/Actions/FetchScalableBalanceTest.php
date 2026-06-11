<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Prices\FetchScalableBalance;
use App\Models\Asset;
use App\Models\Category;
use App\Models\ScalableConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'services.scalable.cli.enabled' => true,
            'services.scalable.cash_category_id' => $this->cashId,
            'services.scalable.cash_asset_name' => 'Scalable Liquidità',
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
        $this->fakeCli([
            ['isin' => 'IE00B6R52259', 'name' => 'iShares MSCI ACWI (Acc)', 'value' => 1000.0],
        ], total: 1500.0);

        $result = app(FetchScalableBalance::class)->run();

        $this->assertSame([], $result->failed);
        $acwi->refresh();
        $cash->refresh();
        $this->assertEqualsWithDelta(1000.0, (float) $acwi->value, 0.001);
        // Cash is derived as total - securities - crypto.
        $this->assertEqualsWithDelta(500.0, (float) $cash->value, 0.001);
        $this->assertNotNull($acwi->synced_at);
        $this->assertSame(Asset::SYNC_SOURCE_BROKER, $acwi->sync_source);
        // The asset keeps its own display name; the CLI name is ignored.
        $this->assertSame('ACWI', $acwi->name);
        // A successful sync records healthy connection state.
        $this->assertSame(ScalableConnection::SYNC_OK, ScalableConnection::current()->last_sync_status);
    }

    public function test_skips_a_position_no_asset_carries(): void
    {
        $this->fakeCli([
            ['isin' => 'UNTRACKED', 'name' => 'Mystery', 'value' => 50.0],
        ], total: 50.0);

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
        $this->fakeCli([
            ['isin' => 'IE00B6R52259', 'name' => 'ACWI', 'value' => 650.0],
        ], total: 650.0);

        app(FetchScalableBalance::class)->run();

        // A fresh current-month row carries the new value and the ISIN.
        $this->assertDatabaseHas('assets', [
            'name' => 'ACWI', 'date' => now()->format('Y-m-01'), 'value' => 650.0, 'isin' => 'IE00B6R52259',
        ]);
        $this->assertSame(2, Asset::where('isin', 'IE00B6R52259')->count());
    }

    public function test_fails_when_the_session_lapsed(): void
    {
        $acwi = Asset::factory()->create(['category_id' => $this->stocksId, 'name' => 'ACWI', 'isin' => 'IE00B6R52259', 'value' => 999, 'date' => now()->format('Y-m-01')]);
        $this->fakeCli([], total: 0, loggedIn: false);

        $result = app(FetchScalableBalance::class)->run();

        $this->assertContains('Scalable', $result->failed);
        $acwi->refresh();
        $this->assertEqualsWithDelta(999.0, (float) $acwi->value, 0.001);
        $this->assertNull($acwi->synced_at);
        // A failed sync is recorded so Settings can surface it after the toast.
        $connection = ScalableConnection::current();
        $this->assertSame(ScalableConnection::SYNC_FAILED, $connection->last_sync_status);
        $this->assertNotNull($connection->last_sync_error);
        // A lapsed session is detected by whoami, before any broker command runs.
        Process::assertNotRan(fn ($process): bool => str_contains(
            is_array($process->command) ? implode(' ', $process->command) : (string) $process->command,
            'broker',
        ));
    }

    public function test_inert_when_the_cli_is_disabled(): void
    {
        config(['services.scalable.cli.enabled' => false]);
        Process::fake();

        $result = app(FetchScalableBalance::class)->run();

        $this->assertSame([], $result->updated);
        $this->assertSame([], $result->failed);
        Process::assertNothingRan();
    }
}
