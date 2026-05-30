<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Input\ResolveSnapshotState;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Snapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResolveSnapshotStateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_returns_missing_when_no_snapshot_exists_for_the_month(): void
    {
        $this->assertSame('missing', app(ResolveSnapshotState::class)->run('2026-02-01'));
    }

    public function test_returns_current_when_snapshot_is_newer_than_asset_edits(): void
    {
        $cat = Category::factory()->create();

        Carbon::setTestNow('2026-02-10 09:00:00');
        Asset::factory()->create(['category_id' => $cat->id, 'date' => '2026-02-01', 'value' => 100]);

        Carbon::setTestNow('2026-02-10 12:00:00');
        Snapshot::create(['date' => '2026-02-10', 'total_value' => 100]);

        $this->assertSame('current', app(ResolveSnapshotState::class)->run('2026-02-01'));
    }

    public function test_returns_stale_when_an_asset_was_edited_after_the_snapshot(): void
    {
        $cat = Category::factory()->create();

        Carbon::setTestNow('2026-02-10 09:00:00');
        $asset = Asset::factory()->create(['category_id' => $cat->id, 'date' => '2026-02-01', 'value' => 100]);

        Carbon::setTestNow('2026-02-10 12:00:00');
        Snapshot::create(['date' => '2026-02-10', 'total_value' => 100]);

        Carbon::setTestNow('2026-02-10 15:00:00');
        $asset->update(['value' => 200]);

        $this->assertSame('stale', app(ResolveSnapshotState::class)->run('2026-02-01'));
    }

    public function test_uses_the_latest_snapshot_in_the_month(): void
    {
        $cat = Category::factory()->create();

        Carbon::setTestNow('2026-02-20 09:00:00');
        $asset = Asset::factory()->create(['category_id' => $cat->id, 'date' => '2026-02-01', 'value' => 100]);

        // An early snapshot, then an asset edit, then a later snapshot that supersedes it.
        Carbon::setTestNow('2026-02-05 12:00:00');
        Snapshot::create(['date' => '2026-02-05', 'total_value' => 100]);

        Carbon::setTestNow('2026-02-20 15:00:00');
        $asset->update(['value' => 200]);

        Carbon::setTestNow('2026-02-20 16:00:00');
        Snapshot::create(['date' => '2026-02-20', 'total_value' => 200]);

        // The latest snapshot (02-20 16:00) is newer than the asset edit (02-20 15:00).
        $this->assertSame('current', app(ResolveSnapshotState::class)->run('2026-02-01'));
    }

    public function test_a_snapshot_in_another_month_does_not_count(): void
    {
        $cat = Category::factory()->create();
        Asset::factory()->create(['category_id' => $cat->id, 'date' => '2026-02-01', 'value' => 100]);

        // Snapshot belongs to January, not the queried February.
        Snapshot::create(['date' => '2026-01-31', 'total_value' => 50]);

        $this->assertSame('missing', app(ResolveSnapshotState::class)->run('2026-02-01'));
    }
}
