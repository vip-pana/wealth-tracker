<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    // These tests assert on the guest experience, so the base class must not
    // sign a user in for them.
    protected bool $authenticate = false;

    private function user(string $password = 'correct-horse-battery-1'): User
    {
        return User::factory()->create([
            'email' => 'me@example.com',
            'password' => $password,
        ]);
    }

    public function test_login_page_is_reachable_once_an_account_exists(): void
    {
        $this->user();

        $this->get('/login')->assertOk();
    }

    public function test_logs_in_with_correct_credentials(): void
    {
        $this->user();

        $this->post('/login', [
            'email' => 'me@example.com',
            'password' => 'correct-horse-battery-1',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
    }

    public function test_rejects_a_wrong_password(): void
    {
        $this->user();

        $this->post('/login', [
            'email' => 'me@example.com',
            'password' => 'wrong-password-entirely',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * A single password on a public URL is a brute-force target, so the attempt
     * must be throttled rather than merely rejected.
     */
    public function test_throttles_repeated_failures(): void
    {
        $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'me@example.com',
                'password' => 'wrong-password-entirely',
            ]);
        }

        $response = $this->post('/login', [
            'email' => 'me@example.com',
            'password' => 'correct-horse-battery-1',
        ]);

        // Even the correct password is refused while the lockout stands.
        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        RateLimiter::clear('me@example.com|127.0.0.1');
    }

    public function test_logs_out(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->user();

        $this->get('/')->assertRedirect('/login');
    }

    /**
     * The routes are protected by a group, so the risk is a route declared
     * outside it. These cover one page and one mutating endpoint per area.
     */
    public function test_every_app_surface_requires_authentication(): void
    {
        $this->user();

        foreach (['/', '/input', '/advisor', '/settings', '/goal', '/cashflow', '/export/csv'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }

        foreach (['/backup', '/snapshots', '/prices/refresh', '/notifications/read-all'] as $path) {
            $this->post($path)->assertRedirect('/login');
        }
    }

    /**
     * The bank redirects the browser here after consent and cannot present our
     * session. Requiring auth would break the consent flow against the real
     * bank only, so it is pinned by a test.
     */
    public function test_bank_callback_stays_open_to_guests(): void
    {
        $this->user();

        $this->assertGuest();

        // Not a redirect to /login: the controller handles the request itself
        // (rejecting an unknown state, which is the correct outcome here).
        $this->get('/banking/callback')->assertRedirectContains('/settings');
    }

    public function test_authenticated_users_are_kept_off_the_login_page(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/login')->assertRedirect('/');
    }
}
