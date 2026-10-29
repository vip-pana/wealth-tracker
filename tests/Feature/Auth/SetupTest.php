<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Auth\SetupToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    // These tests assert on the guest experience, so the base class must not
    // sign a user in for them.
    protected bool $authenticate = false;

    private string $tokenPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Point the token at a scratch file: the default lives in storage/app,
        // which is the real (bind-mounted) data directory, and a test suite has
        // no business minting secrets into it.
        $this->tokenPath = sys_get_temp_dir().'/wt-setup-token-'.getmypid().'.txt';
        config()->set('auth.setup_token_path', $this->tokenPath);
        File::delete($this->tokenPath);
    }

    protected function tearDown(): void
    {
        File::delete($this->tokenPath);

        parent::tearDown();
    }

    private function token(): SetupToken
    {
        return $this->app->make(SetupToken::class);
    }

    /** @return array<string, string> */
    private function valid(array $overrides = []): array
    {
        return [
            'token' => $this->token()->current(),
            'email' => 'me@example.com',
            'password' => 'correct-horse-1',
            'password_confirmation' => 'correct-horse-1',
            ...$overrides,
        ];
    }

    public function test_a_fresh_install_is_sent_to_setup(): void
    {
        $this->get('/')->assertRedirect('/setup');
        $this->get('/login')->assertRedirect('/setup');
    }

    public function test_creates_the_account_and_signs_in(): void
    {
        $this->post('/setup', $this->valid())->assertRedirect('/');

        $this->assertAuthenticated();
        $user = User::query()->sole();
        $this->assertSame('me@example.com', $user->email);
        $this->assertTrue(Hash::check('correct-horse-1', $user->password));
    }

    public function test_the_password_is_not_stored_in_plain_text(): void
    {
        $this->post('/setup', $this->valid());

        $this->assertNotSame('correct-horse-1', User::query()->sole()->password);
    }

    /**
     * /setup creates the account that owns every financial record. Once one
     * exists the route has to be closed server-side — hiding the link would
     * leave anyone able to POST a second account into being.
     */
    public function test_setup_is_closed_once_an_account_exists(): void
    {
        $token = $this->token()->current();
        User::factory()->create();

        $this->get('/setup')->assertRedirect('/');

        $this->post('/setup', [
            'token' => $token,
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
        $this->post('/setup', $this->valid())->assertRedirect('/');

        $this->assertSame('Owner', User::query()->sole()->name);
    }

    public function test_rejects_a_short_password(): void
    {
        $this->post('/setup', $this->valid(['password' => 'short-1', 'password_confirmation' => 'short-1']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, User::query()->count());
    }

    public function test_rejects_a_password_without_a_number(): void
    {
        $this->post('/setup', $this->valid(['password' => 'letters-only-here', 'password_confirmation' => 'letters-only-here']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, User::query()->count());
    }

    public function test_rejects_a_mismatched_confirmation(): void
    {
        $this->post('/setup', $this->valid(['password_confirmation' => 'something-else-1']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, User::query()->count());
    }

    // ─── The setup token ─────────────────────────────────────────────────────

    /**
     * The land grab this closes: on an instance reachable from the internet,
     * whoever loads /setup first would otherwise own every financial record in
     * it. Knowing the password rules is not enough — you have to have been on
     * the machine.
     */
    public function test_a_stranger_without_the_code_cannot_claim_the_instance(): void
    {
        $this->post('/setup', $this->valid(['token' => 'not-the-token']))
            ->assertSessionHasErrors('token');

        $this->assertSame(0, User::query()->count());
        $this->assertGuest();
    }

    public function test_the_code_is_required(): void
    {
        $this->post('/setup', $this->valid(['token' => '']))
            ->assertSessionHasErrors('token');

        $this->assertSame(0, User::query()->count());
    }

    /**
     * The owner reads the code off the filesystem or out of the logs, so
     * opening the page has to be enough to produce one — an install that never
     * ran the entrypoint (`composer dev`) must not be left with an unguarded
     * /setup.
     */
    public function test_opening_the_page_mints_a_code_and_says_where_to_find_it(): void
    {
        $this->assertFileDoesNotExist($this->tokenPath);

        $this->get('/setup')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Setup')->where('tokenPath', $this->tokenPath));

        $this->assertFileExists($this->tokenPath);
        // Never handed to the browser: the whole point is that it only reaches
        // someone with access to the machine.
        $this->assertStringNotContainsString($this->token()->current(), $this->get('/setup')->getContent());
    }

    /**
     * Readable by the owner, by nobody else sharing the host. The file sits in
     * the bind-mounted data directory, which on a shared box is a normal
     * directory other accounts can list.
     */
    public function test_the_code_file_is_not_readable_by_anyone_else(): void
    {
        $this->token()->current();

        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->tokenPath)), -4));
    }

    /**
     * A live secret with nothing left to guard is just something to leak —
     * and the data directory it sits in is copied into every nightly backup.
     */
    public function test_the_code_is_destroyed_once_the_account_exists(): void
    {
        $this->post('/setup', $this->valid())->assertRedirect('/');

        $this->assertFileDoesNotExist($this->tokenPath);
    }

    public function test_regenerating_invalidates_the_previous_code(): void
    {
        $old = $this->token()->current();
        $this->token()->regenerate();

        $this->assertFalse($this->token()->verify($old));
        $this->assertTrue($this->token()->verify($this->token()->current()));
    }

    public function test_the_command_prints_the_code_and_can_replace_it(): void
    {
        $first = $this->token()->current();

        $this->artisan('setup:token')->expectsOutputToContain($first)->assertSuccessful();

        $this->artisan('setup:token', ['--new' => true])->assertSuccessful();
        $this->assertNotSame($first, $this->token()->current());
    }

    /**
     * The prod entrypoint runs this on every start. Once an account exists it
     * must stay quiet rather than printing a secret into the logs of a running
     * instance for the rest of its life.
     */
    public function test_the_command_prints_nothing_once_an_account_exists(): void
    {
        User::factory()->create();

        $this->artisan('setup:token')->assertSuccessful();

        $this->assertFileDoesNotExist($this->tokenPath);
    }
}
