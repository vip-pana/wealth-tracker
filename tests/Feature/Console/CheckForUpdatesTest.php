<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckForUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private const string COMMIT = 'abc123def456abc123def456abc123def456abcd';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'updates.repository' => 'owner/repo',
            'updates.branch' => 'main',
            'updates.commit' => self::COMMIT,
            'updates.github_token' => null,
        ]);
    }

    private function fakeCompare(int $aheadBy): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(['ahead_by' => $aheadBy, 'status' => 'ahead']),
        ]);
    }

    /**
     * Successive calls returning different results. A second Http::fake() does
     * not replace the first — the stubs accumulate and the original keeps
     * answering — so a sequence is the only way to model "behind, then updated".
     *
     * @param  list<int>  $aheadByPerCall
     */
    private function fakeCompareSequence(array $aheadByPerCall): void
    {
        $sequence = Http::sequence();
        foreach ($aheadByPerCall as $aheadBy) {
            $sequence->push(['ahead_by' => $aheadBy, 'status' => 'ahead']);
        }

        Http::fake(['api.github.com/*' => $sequence]);
    }

    public function test_notifies_when_behind(): void
    {
        $this->fakeCompare(3);

        $this->artisan('updates:check')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_UPDATE_AVAILABLE,
        ]);
        $this->assertStringContainsString('3 aggiornamenti', (string) Notification::query()->value('title'));
    }

    public function test_says_one_update_in_the_singular(): void
    {
        $this->fakeCompare(1);

        $this->artisan('updates:check');

        $this->assertStringContainsString('Un aggiornamento', (string) Notification::query()->value('title'));
    }

    public function test_stays_quiet_when_up_to_date(): void
    {
        $this->fakeCompare(0);

        $this->artisan('updates:check')->assertSuccessful();

        $this->assertSame(0, Notification::query()->count());
    }

    /**
     * The check runs daily; a standing notice must not become a new row every
     * morning until the user updates.
     */
    public function test_does_not_stack_up_daily_notifications(): void
    {
        $this->fakeCompare(2);

        $this->artisan('updates:check');
        $this->artisan('updates:check');
        $this->artisan('updates:check');

        $this->assertSame(1, Notification::query()->count());
    }

    public function test_clears_the_notice_once_updated(): void
    {
        $this->fakeCompareSequence([2, 0]);

        $this->artisan('updates:check');
        $this->assertSame(1, Notification::query()->unread()->count());

        $this->artisan('updates:check');

        $this->assertSame(0, Notification::query()->unread()->count());
    }

    /**
     * Updating to a build that is still behind must replace the notice rather
     * than leave the old count standing — hence the deployed commit in the
     * dedupe key.
     */
    public function test_a_new_build_replaces_the_previous_notice(): void
    {
        $this->fakeCompareSequence([5, 2]);

        $this->artisan('updates:check');

        config(['updates.commit' => str_repeat('f', 40)]);
        $this->artisan('updates:check');

        $unread = Notification::query()->unread()->orderBy('id')->get();
        $this->assertCount(2, $unread);
        $this->assertStringContainsString('2 aggiornamenti', (string) $unread->last()?->title);
    }

    /**
     * A network blip gives the user nothing to act on, and a daily false alarm
     * trains them to ignore the bell.
     */
    public function test_a_failed_request_raises_no_notification(): void
    {
        Http::fake(['api.github.com/*' => Http::response('', 500)]);

        $this->artisan('updates:check')->assertSuccessful();

        $this->assertSame(0, Notification::query()->count());
    }

    /**
     * 404 means the deployed commit is not on the remote — a local build, or
     * rewritten history. Not an error worth reporting.
     */
    public function test_an_unknown_commit_is_not_an_error(): void
    {
        Http::fake(['api.github.com/*' => Http::response('', 404)]);

        $this->artisan('updates:check')->assertSuccessful();

        $this->assertSame(0, Notification::query()->count());
    }

    /**
     * An image built without the GIT_COMMIT arg cannot know what it runs, and a
     * wrong answer is worse than none.
     */
    public function test_an_unstamped_build_checks_nothing(): void
    {
        config(['updates.commit' => 'unknown']);
        Http::fake();

        $this->artisan('updates:check')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_can_be_disabled(): void
    {
        config(['updates.repository' => null]);
        Http::fake();

        $this->artisan('updates:check')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_sends_the_token_when_configured(): void
    {
        config(['updates.github_token' => 'ghp_secret']);
        $this->fakeCompare(1);

        $this->artisan('updates:check');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer ghp_secret'));
    }

    public function test_compares_the_running_commit_against_the_branch(): void
    {
        $this->fakeCompare(1);

        $this->artisan('updates:check');

        Http::assertSent(fn ($request) => str_contains(
            (string) $request->url(),
            'compare/'.self::COMMIT.'...main',
        ));
    }
}
