<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Transactions\ImportScalableTransactions;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ImportScalableTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private int $stocksId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stocksId = Category::factory()->create()->id;
        config(['services.scalable.cli.enabled' => true]);
    }

    /** @param array<string, mixed> $data */
    private function cliEnvelope(array $data): string
    {
        return (string) json_encode(['ok' => true, 'data' => $data]);
    }

    /**
     * @param  list<array{id: string, isin: string, quantity: float, amount: float, side: string}>  $items
     */
    private function transactionsResult(array $items, ?string $cursor = null): string
    {
        return $this->cliEnvelope([
            'result' => [
                'cursor' => $cursor,
                'items' => array_map(fn (array $i): array => [
                    'id' => $i['id'],
                    'isin' => $i['isin'],
                    'description' => 'Some Security',
                    'quantity' => $i['quantity'],
                    'amount' => $i['amount'],
                    'side' => $i['side'],
                    'security_transaction_type' => 'SAVINGS_PLAN',
                    'status' => 'SETTLED',
                    'last_event_datetime' => '2026-06-04T10:27:57.666Z',
                ], $items),
            ],
        ]);
    }

    private function acwi(): Asset
    {
        return Asset::factory()->create([
            'category_id' => $this->stocksId,
            'name' => 'ACWI',
            'isin' => 'IE00B6R52259',
            'ticker' => 'ACWI',
            'quantity' => 0,
            'date' => now()->format('Y-m-01'),
        ]);
    }

    public function test_imports_transactions_and_matches_by_isin(): void
    {
        $asset = $this->acwi();

        Process::fake([
            '*whoami*' => Process::result($this->cliEnvelope(['result' => ['personOverview' => ['id' => 'x']]])),
            '*broker*transactions*' => Process::result($this->transactionsResult([
                ['id' => 'tx-1', 'isin' => 'IE00B6R52259', 'quantity' => 3.0, 'amount' => -312.78, 'side' => 'BUY'],
            ])),
        ]);

        $summary = app(ImportScalableTransactions::class)->run();

        $this->assertSame(1, $summary['imported']);
        $this->assertSame([], $summary['skipped_isins']);

        $this->assertDatabaseHas('transactions', [
            'external_id' => 'tx-1',
            'asset_id' => $asset->id,
            'type' => 'buy',
        ]);

        $tx = Transaction::where('external_id', 'tx-1')->firstOrFail();
        $this->assertSame(3.0, $tx->shares);
        $this->assertEqualsWithDelta(104.26, $tx->price_per_share, 0.001); // 312.78 / 3

        // The asset's quantity is synced from the imported transactions.
        $this->assertSame(3.0, $asset->fresh()->quantity);
    }

    public function test_skips_isins_no_asset_carries(): void
    {
        $this->acwi();

        Process::fake([
            '*whoami*' => Process::result($this->cliEnvelope(['result' => ['personOverview' => ['id' => 'x']]])),
            '*broker*transactions*' => Process::result($this->transactionsResult([
                ['id' => 'tx-1', 'isin' => 'IE00B6R52259', 'quantity' => 3.0, 'amount' => -300.0, 'side' => 'BUY'],
                ['id' => 'tx-2', 'isin' => 'XX0000000000', 'quantity' => 1.0, 'amount' => -50.0, 'side' => 'BUY'],
            ])),
        ]);

        $summary = app(ImportScalableTransactions::class)->run();

        $this->assertSame(1, $summary['imported']);
        $this->assertSame(['XX0000000000'], $summary['skipped_isins']);
        $this->assertDatabaseMissing('transactions', ['external_id' => 'tx-2']);
    }

    public function test_reimport_is_idempotent(): void
    {
        $this->acwi();

        $fake = fn () => Process::fake([
            '*whoami*' => Process::result($this->cliEnvelope(['result' => ['personOverview' => ['id' => 'x']]])),
            '*broker*transactions*' => Process::result($this->transactionsResult([
                ['id' => 'tx-1', 'isin' => 'IE00B6R52259', 'quantity' => 3.0, 'amount' => -300.0, 'side' => 'BUY'],
            ])),
        ]);

        $fake();
        app(ImportScalableTransactions::class)->run();
        $fake();
        app(ImportScalableTransactions::class)->run();

        $this->assertSame(1, Transaction::where('external_id', 'tx-1')->count());
    }

    public function test_returns_null_when_cli_disabled(): void
    {
        config(['services.scalable.cli.enabled' => false]);

        $this->assertNull(app(ImportScalableTransactions::class)->run());
    }

    public function test_returns_null_without_a_session(): void
    {
        Process::fake([
            '*whoami*' => Process::result((string) json_encode(['ok' => false, 'error' => ['code' => 'no_session']])),
        ]);

        $this->assertNull(app(ImportScalableTransactions::class)->run());
    }
}
