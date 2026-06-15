<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\PushNotification;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function push(): PushNotification
    {
        return app(PushNotification::class);
    }

    public function test_creates_a_one_shot_notification(): void
    {
        $this->push()->run(
            type: Notification::TYPE_ADVISOR_REPORT_READY,
            level: Notification::LEVEL_SUCCESS,
            title: 'Analisi completata',
        );

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['type' => Notification::TYPE_ADVISOR_REPORT_READY, 'read_at' => null]);
    }

    public function test_deduped_notification_does_not_stack_while_unread(): void
    {
        $key = 'scalable_sync_failed';

        $this->push()->run(type: 'x', level: 'warning', title: 'fail', dedupeKey: $key);
        $this->push()->run(type: 'x', level: 'warning', title: 'fail', dedupeKey: $key);
        $this->push()->run(type: 'x', level: 'warning', title: 'fail', dedupeKey: $key);

        // Three failures, one unread row.
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_a_new_deduped_row_is_created_once_the_prior_one_is_read(): void
    {
        $key = 'scalable_sync_failed';

        $first = $this->push()->run(type: 'x', level: 'warning', title: 'fail', dedupeKey: $key);
        $first->markRead();

        // The condition recurs after the user dismissed it: a fresh row appears.
        $this->push()->run(type: 'x', level: 'warning', title: 'fail', dedupeKey: $key);

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(1, Notification::query()->unread()->count());
    }

    public function test_resolve_marks_the_unread_deduped_row_read(): void
    {
        $key = 'scalable_sync_failed';
        $this->push()->run(type: 'x', level: 'warning', title: 'fail', dedupeKey: $key);

        $this->push()->resolve($key);

        $this->assertSame(0, Notification::query()->unread()->count());
    }

    public function test_resolve_is_a_noop_when_nothing_matches(): void
    {
        $this->push()->resolve('nothing_here');

        $this->assertDatabaseCount('notifications', 0);
    }
}
