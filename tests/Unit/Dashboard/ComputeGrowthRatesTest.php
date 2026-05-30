<?php

declare(strict_types=1);

namespace Tests\Unit\Dashboard;

use App\Actions\Dashboard\ComputeGrowthRates;
use App\Models\Snapshot;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ComputeGrowthRatesTest extends TestCase
{
    /** @param array<int, array{date: string, total_value: float}> $rows */
    private function snapshots(array $rows): Collection
    {
        return collect($rows)->map(fn (array $r): Snapshot => new Snapshot([
            'date' => $r['date'],
            'total_value' => $r['total_value'],
        ]));
    }

    public function test_returns_empty_for_a_single_snapshot(): void
    {
        $result = (new ComputeGrowthRates)->run($this->snapshots([
            ['date' => '2025-01-01', 'total_value' => 1000],
        ]));

        $this->assertSame([], $result);
    }

    public function test_produces_n_minus_one_consecutive_deltas(): void
    {
        $result = (new ComputeGrowthRates)->run($this->snapshots([
            ['date' => '2025-01-01', 'total_value' => 1000],
            ['date' => '2025-02-01', 'total_value' => 1100],
            ['date' => '2025-03-01', 'total_value' => 1045],
        ]));

        $this->assertCount(2, $result);
        $this->assertSame('2025-02-01', $result[0]['date']);
        $this->assertSame(10.0, $result[0]['change_pct']);   // +10%
        $this->assertSame('2025-03-01', $result[1]['date']);
        $this->assertSame(-5.0, $result[1]['change_pct']);   // 1100 -> 1045
    }

    public function test_skips_delta_when_previous_total_is_zero(): void
    {
        $result = (new ComputeGrowthRates)->run($this->snapshots([
            ['date' => '2025-01-01', 'total_value' => 0],
            ['date' => '2025-02-01', 'total_value' => 500],
        ]));

        // Cannot divide by zero, so the first transition is skipped.
        $this->assertSame([], $result);
    }

    public function test_rounds_to_two_decimals(): void
    {
        $result = (new ComputeGrowthRates)->run($this->snapshots([
            ['date' => '2025-01-01', 'total_value' => 3000],
            ['date' => '2025-02-01', 'total_value' => 3100],
        ]));

        $this->assertSame(3.33, $result[0]['change_pct']);
    }
}
