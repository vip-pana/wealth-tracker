<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_marking_one_read_removes_it_from_the_unread_feed(): void
    {
        $n = Notification::create(['type' => 'x', 'level' => 'info', 'title' => 'hi']);

        $this->post("/notifications/{$n->id}/read")->assertRedirect();

        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_mark_all_read_clears_the_unread_feed(): void
    {
        Notification::create(['type' => 'x', 'level' => 'info', 'title' => 'a']);
        Notification::create(['type' => 'y', 'level' => 'warning', 'title' => 'b']);

        $this->post('/notifications/read-all')->assertRedirect();

        $this->assertSame(0, Notification::query()->unread()->count());
    }

    public function test_unread_notifications_are_shared_to_every_page(): void
    {
        Notification::create(['type' => 'x', 'level' => 'warning', 'title' => 'Attenzione']);

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->has('notifications', 1)
                ->where('notifications.0.title', 'Attenzione')
            );
    }

    public function test_read_notifications_are_not_shared(): void
    {
        $n = Notification::create(['type' => 'x', 'level' => 'info', 'title' => 'letta']);
        $n->markRead();

        $this->get('/')->assertInertia(fn ($page) => $page->has('notifications', 0));
    }
}
