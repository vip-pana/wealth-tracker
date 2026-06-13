<?php

declare(strict_types=1);

namespace Tests\Unit\Transactions;

use App\Actions\Transactions\ComputePosition;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ComputePositionTest extends TestCase
{
    /**
     * @param  array<int, array{type?: string, shares: float, price: float, date: string, fees?: float, id?: int}>  $rows
     * @return Collection<int, Transaction>
     */
    private function transactions(array $rows): Collection
    {
        return collect($rows)->map(function (array $r, int $i): Transaction {
            $tx = new Transaction([
                'type' => $r['type'] ?? Transaction::TYPE_BUY,
                'shares' => $r['shares'],
                'price_per_share' => $r['price'],
                'fees' => $r['fees'] ?? null,
                'date' => $r['date'],
            ]);
            $tx->id = $r['id'] ?? $i + 1;

            return $tx;
        });
    }

    public function test_empty_history_is_a_flat_position(): void
    {
        $result = (new ComputePosition)->run(collect());

        $this->assertSame(0.0, $result['shares']);
        $this->assertSame(0.0, $result['average_cost']);
        $this->assertSame(0.0, $result['cost_basis']);
        $this->assertNull($result['current_value']);
    }

    public function test_weighted_average_cost_across_multiple_buys(): void
    {
        $result = (new ComputePosition)->run($this->transactions([
            ['shares' => 10, 'price' => 100, 'date' => '2025-01-01'],
            ['shares' => 10, 'price' => 120, 'date' => '2025-02-01'],
        ]));

        $this->assertSame(20.0, $result['shares']);
        $this->assertSame(110.0, $result['average_cost']); // (1000 + 1200) / 20
        $this->assertSame(2200.0, $result['cost_basis']);
        $this->assertSame(0.0, $result['realised_pnl']);
    }

    public function test_partial_sell_keeps_average_and_realises_gain(): void
    {
        $result = (new ComputePosition)->run($this->transactions([
            ['shares' => 10, 'price' => 100, 'date' => '2025-01-01'],
            ['shares' => 10, 'price' => 120, 'date' => '2025-02-01'],          // avg 110, 20 shares
            ['type' => Transaction::TYPE_SELL, 'shares' => 5, 'price' => 130, 'date' => '2025-03-01'],
        ]));

        $this->assertSame(15.0, $result['shares']);
        $this->assertSame(110.0, $result['average_cost']);  // unchanged by a sell
        $this->assertSame(1650.0, $result['cost_basis']);   // 15 * 110
        $this->assertSame(100.0, $result['realised_pnl']);  // 5 * (130 - 110)
    }

    public function test_full_exit_zeroes_out_cleanly(): void
    {
        $result = (new ComputePosition)->run($this->transactions([
            ['shares' => 10, 'price' => 100, 'date' => '2025-01-01'],
            ['type' => Transaction::TYPE_SELL, 'shares' => 10, 'price' => 150, 'date' => '2025-02-01'],
        ]));

        $this->assertSame(0.0, $result['shares']);
        $this->assertSame(0.0, $result['average_cost']);
        $this->assertSame(0.0, $result['cost_basis']);
        $this->assertSame(500.0, $result['realised_pnl']); // 10 * (150 - 100)
    }

    public function test_unrealised_pnl_with_a_live_price(): void
    {
        $result = (new ComputePosition)->run($this->transactions([
            ['shares' => 10, 'price' => 100, 'date' => '2025-01-01'],
            ['shares' => 10, 'price' => 120, 'date' => '2025-02-01'],   // 20 shares, cost 2200
        ]), livePrice: 130.0);

        $this->assertSame(2600.0, $result['current_value']);    // 20 * 130
        $this->assertSame(400.0, $result['unrealised_pnl']);    // 2600 - 2200
        $this->assertSame(18.18, $result['unrealised_pnl_pct']); // 400 / 2200
    }

    public function test_buy_fees_are_folded_into_cost(): void
    {
        $result = (new ComputePosition)->run($this->transactions([
            ['shares' => 10, 'price' => 100, 'date' => '2025-01-01', 'fees' => 5],
        ]));

        $this->assertSame(100.5, $result['average_cost']); // (1000 + 5) / 10
        $this->assertSame(1005.0, $result['cost_basis']);
    }

    public function test_processes_chronologically_regardless_of_input_order(): void
    {
        // Same trades as the partial-sell case but shuffled; result must match.
        $result = (new ComputePosition)->run($this->transactions([
            ['type' => Transaction::TYPE_SELL, 'shares' => 5, 'price' => 130, 'date' => '2025-03-01', 'id' => 3],
            ['shares' => 10, 'price' => 120, 'date' => '2025-02-01', 'id' => 2],
            ['shares' => 10, 'price' => 100, 'date' => '2025-01-01', 'id' => 1],
        ]));

        $this->assertSame(15.0, $result['shares']);
        $this->assertSame(110.0, $result['average_cost']);
        $this->assertSame(100.0, $result['realised_pnl']);
    }
}
