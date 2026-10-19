<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The expensive routes are throttled, and a throttled request comes back as
 * something the user can read rather than Laravel's bare 429 page.
 *
 * Tests exhaust a limiter by hand (RateLimiter::hit) instead of sending the
 * real number of requests: a backup is a VACUUM INTO and a price refresh is an
 * HTTP call, so firing them to the limit would be slow and would assert on the
 * controller rather than on the throttle.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Burn every attempt in a named limiter for the signed-in user, so the next
     * real request through it is the one that gets refused.
     */
    private function exhaust(string $limiter): void
    {
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $this->app['auth']->user());

        /** @var Limit $limit */
        $limit = RateLimiter::limiter($limiter)($request);

        // The same key ThrottleRequests derives for a named limiter.
        $key = md5($limiter.$limit->key);

        for ($i = 0; $i < $limit->maxAttempts; $i++) {
            RateLimiter::hit($key, $limit->decaySeconds);
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function throttledRoutes(): array
    {
        return [
            'backup' => ['backup', 'POST', '/backup'],
            'csv import' => ['import', 'POST', '/import/csv'],
            'bank consent' => ['banking', 'POST', '/banking/connect'],
            'broker login' => ['scalable-login', 'POST', '/scalable/cli/login'],
            'price refresh' => ['prices', 'POST', '/prices/refresh'],
            'advisor chat' => ['advisor-generate', 'POST', '/advisor/chat'],
            'advisor report' => ['advisor-generate', 'POST', '/advisor/generate'],
        ];
    }

    #[DataProvider('throttledRoutes')]
    public function test_route_is_throttled(string $limiter, string $method, string $uri): void
    {
        $this->exhaust($limiter);

        $this->call($method, $uri)->assertStatus(429);
    }

    public function test_an_inertia_visit_bounces_back_with_a_readable_error(): void
    {
        $this->exhaust('backup');

        $this->post('/backup', [], ['X-Inertia' => 'true'])
            ->assertStatus(303)
            ->assertSessionHas('error');
    }

    public function test_a_json_caller_gets_a_readable_error(): void
    {
        $this->exhaust('advisor-generate');

        $this->postJson('/advisor/chat', ['message' => 'ciao'])
            ->assertStatus(429)
            ->assertJsonStructure(['error']);
    }

    /**
     * The advisor's status endpoint is polled every 2.5s per open tab while a
     * reply generates. A 60/min limit would fail the user's own conversation as
     * soon as they opened a second tab, so it has its own, much higher one.
     */
    public function test_the_polling_endpoint_has_headroom_for_several_tabs(): void
    {
        $request = Request::create('/', 'GET');

        /** @var Limit $poll */
        $poll = RateLimiter::limiter('advisor-poll')($request);

        $this->assertGreaterThanOrEqual(120, $poll->maxAttempts);
        $this->assertSame(60, $poll->decaySeconds);
    }
}
