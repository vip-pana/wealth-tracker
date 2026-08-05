<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    // These tests assert on the guest experience, so the base class must not
    // sign a user in for them.
    protected bool $authenticate = false;

    private const array VALID = [
        'email' => 'me@example.com',
        'password' => 'correct-horse-1',
        'password_confirmation' => 'correct-horse-1',
    ];

    public function test_a_fresh_install_is_sent_to_setup(): void
    {
        $this->get('/')->assertRedirect('/setup');
        $this->get('/login')->assertRedirect('/setup');
    }

    public function test_creates_the_account_and_signs_in(): void
    {
        $this->post('/setup', self::VALID)->assertRedirect('/');

        $this->assertAuthenticated();
        $user = User::query()->sole();
        $this->assertSame('me@example.com', $user->email);
        $this->assertTrue(Hash::check('correct-horse-1', $user->password));
    }

    public function test_the_password_is_not_stored_in_plain_text(): void
    {
        $this->post('/setup', self::VALID);

        $this->assertNotSame('correct-horse-1', User::query()->sole()->password);
    }

    /**
     * /setup creates the account that owns every financial record. Once one
     * exists the route has to be closed server-side — hiding the link would
     * leave anyone able to POST a second account into being.
     */
    public function test_setup_is_closed_once_an_account_exists(): void
    {
        User::factory()->create();

        $this->get('/setup')->assertRedirect('/');

        $this->post('/setup', [
            'email' => 'intruder@example.com',
            'password' => 'another-password-9',
            'password_confirmation' => 'another-password-9',
        ]);

        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
    }

    /**
     * Nothing in the app displays a user's name, so setup does not ask for one.
     * The column is NOT NULL in the default migration, hence the filled value.
     */
    public function test_does_not_ask_for_a_name(): void
    {
        $this->post('/setup', self::VALID)->assertRedirect('/');

        $this->assertSame('Owner', User::query()->sole()->name);
    }

    public function test_rejects_a_short_password(): void
    {
        $this->post('/setup', [...self::VALID, 'password' => 'short-1', 'password_confirmation' => 'short-1'])
            ->assertSessionHasErrors('password');

        $this->assertSame(0, User::query()->count());
    }

    public function test_rejects_a_password_without_a_number(): void
    {
        $this->post('/setup', [...self::VALID, 'password' => 'letters-only-here', 'password_confirmation' => 'letters-only-here'])
            ->assertSessionHasErrors('password');

        $this->assertSame(0, User::query()->count());
    }

    public function test_rejects_a_mismatched_confirmation(): void
    {
        $this->post('/setup', [...self::VALID, 'password_confirmation' => 'something-else-1'])
            ->assertSessionHasErrors('password');

        $this->assertSame(0, User::query()->count());
    }
}
