<?php

declare(strict_types=1);

namespace Tests\Unit\Dashboard;

use App\Actions\Dashboard\ComputeForecast;
use App\Models\Snapshot;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ComputeForecastTest extends TestCase
{
    /** @param array<int, array{date: string, total_value: float}> $rows */
    private function snapshots(array $rows): Collection
    {
        return collect($rows)->map(fn (array $r): Snapshot => new Snapshot([
            'date' => $r['date'],
            'total_value' => $r['total_value'],
        ]));
    }

    public function test_returns_empty_below_two_snapshots(): void
    {
        $this->assertSame([], (new ComputeForecast)->run($this->snapshots([])));
        $this->assertSame([], (new ComputeForecast)->run($this->snapshots([
            ['date' => '2025-01-01', 'total_value' => 1000],
        ])));
    }

    public function test_trend_matches_perfectly_linear_data(): void
    {
        // +100/day over 10-day steps: 2025-01-01..01-31, slope = 10/day.
        $result = (new ComputeForecast)->run($this->snapshots([
            ['date' => '2025-01-01', 'total_value' => 1000],
            ['date' => '2025-01-11', 'total_value' => 1100],
            ['date' => '2025-01-21', 'total_value' => 1200],
            ['date' => '2025-01-31', 'total_value' => 1300],
        ]));

        $historical = array_values(array_filter($result, fn ($p) => $p['actual'] !== null));
        $this->assertCount(4, $historical);

        // On a perfect line the trend equals the actual at each point.
        foreach ($historical as $p) {
            $this->assertEqualsWithDelta($p['actual'], $p['trend'], 0.01);
        }
    }

    public function test_appends_six_monthly_projection_points(): void
    {
        $result = (new ComputeForecast)->run($this->snapshots([
            ['date' => '2025-01-01', 'total_value' => 1000],
            ['date' => '2025-01-31', 'total_value' => 1300],
        ]));

        $projection = array_values(array_filter($result, fn ($p) => $p['forecast'] !== null));
        $this->assertCount(6, $projection);

        // First projection is one month after the last snapshot. Carbon's
        // addMonths overflows a Jan-31 date into early March (no Feb 31).
        $this->assertSame('2025-03-03', $projection[0]['date']);
        // Forecast extends the upward line, so it exceeds the last actual.
        $this->assertGreaterThan(1300, $projection[0]['forecast']);
        // Projections keep rising.
        $this->assertGreaterThan($projection[0]['forecast'], $projection[5]['forecast']);
    }

    public function test_flat_data_forecasts_flat(): void
    {
        $result = (new ComputeForecast)->run($this->snapshots([
            ['date' => '2025-01-01', 'total_value' => 5000],
            ['date' => '2025-02-01', 'total_value' => 5000],
        ]));

        $projection = array_values(array_filter($result, fn ($p) => $p['forecast'] !== null));
        foreach ($projection as $p) {
            $this->assertEqualsWithDelta(5000, $p['forecast'], 0.01);
        }
    }
}
