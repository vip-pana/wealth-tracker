<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Actions\Prices\FetchAllPrices;
use App\Actions\Prices\PriceRefreshResult;
use App\Models\Notification;
use App\Models\Snapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class DailySnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function fakeFetch(PriceRefreshResult $result): void
    {
        $mock = Mockery::mock(FetchAllPrices::class);
        $mock->shouldReceive('run')->once()->andReturn($result);
        $this->app->instance(FetchAllPrices::class, $mock);
    }

    public function test_takes_a_snapshot_when_every_source_is_fresh(): void
    {
        $this->fakeFetch(new PriceRefreshResult(['ACWI', 'BTC'], []));

        $this->artisan('snapshots:daily')->assertSuccessful();

        $this->assertDatabaseHas('snapshots', ['date' => Carbon::now()->toDateString()]);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_skips_and_notifies_when_a_source_is_stale(): void
    {
        $this->fakeFetch(new PriceRefreshResult(['ACWI'], ['Scalable']));

        $this->artisan('snapshots:daily')->assertSuccessful();

        // No snapshot recorded for today…
        $this->assertDatabaseMissing('snapshots', ['date' => Carbon::now()->toDateString()]);
        // …and a warning notification points the user at settings.
        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_SNAPSHOT_SKIPPED,
            'level' => Notification::LEVEL_WARNING,
            'action_url' => '/settings',
        ]);
    }

    public function test_a_repeated_stale_run_does_not_pile_up_notifications(): void
    {
        $this->fakeFetch(new PriceRefreshResult([], ['Scalable']));
        $this->artisan('snapshots:daily')->assertSuccessful();

        // Second run, same stale source: dedupe_key keeps one standing warning.
        $this->fakeFetch(new PriceRefreshResult([], ['Scalable']));
        $this->artisan('snapshots:daily')->assertSuccessful();

        $this->assertSame(1, Notification::query()->where('type', Notification::TYPE_SNAPSHOT_SKIPPED)->count());
    }
}
