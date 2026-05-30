<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Jobs\BackupDatabase;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Snapshot;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SnapshotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_creates_a_snapshot_with_correct_total(): void
    {
        $category = Category::factory()->create();
        Asset::factory()->create(['category_id' => $category->id, 'value' => 1000.00, 'date' => '2025-03-01']);
        Asset::factory()->create(['category_id' => $category->id, 'value' => 500.00, 'date' => '2025-03-01']);

        $this->post('/snapshots', ['date' => '2025-03-01'])->assertRedirect();

        $this->assertDatabaseHas('snapshots', ['date' => '2025-03-01', 'total_value' => 1500.00]);
    }

    public function test_upserts_without_creating_duplicates(): void
    {
        $category = Category::factory()->create();
        Asset::factory()->create(['category_id' => $category->id, 'value' => 1000.00, 'date' => '2025-03-01']);

        $this->post('/snapshots', ['date' => '2025-03-01']);

        Asset::factory()->create(['category_id' => $category->id, 'value' => 200.00, 'date' => '2025-03-01']);

        $this->post('/snapshots', ['date' => '2025-03-01'])->assertRedirect();

        $this->assertSame(1, Snapshot::count());
        $this->assertDatabaseHas('snapshots', ['date' => '2025-03-01', 'total_value' => 1200.00]);
    }

    public function test_saving_a_snapshot_queues_a_backup(): void
    {
        Queue::fake();

        $category = Category::factory()->create();
        Asset::factory()->create(['category_id' => $category->id, 'value' => 1000.00, 'date' => '2025-03-01']);

        $this->post('/snapshots', ['date' => '2025-03-01'])->assertRedirect();

        Queue::assertPushed(BackupDatabase::class);
    }
}
