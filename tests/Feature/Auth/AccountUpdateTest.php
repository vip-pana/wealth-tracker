<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected bool $authenticate = false;

    private const string CURRENT = 'current-password-1';

    private function owner(): User
    {
        return User::factory()->create([
            'email' => 'me@example.com',
            'password' => self::CURRENT,
        ]);
    }

    public function test_updates_the_email(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patch('/account/email', [
            'email' => 'new@example.com',
            'current_password' => self::CURRENT,
        ])->assertRedirect();

        $this->assertSame('new@example.com', $user->fresh()?->email);
    }

    /**
     * The email is the login identifier, so changing it is a credential change:
     * without this check an attacker on an unlocked device could point the
     * account at their own address and lock the owner out.
     */
    public function test_rejects_an_email_change_without_the_current_password(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patch('/account/email', [
            'email' => 'attacker@example.com',
            'current_password' => 'wrong-password-99',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame('me@example.com', $user->fresh()?->email);
    }

    public function test_rejects_a_malformed_email(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patch('/account/email', [
            'email' => 'not-an-email',
            'current_password' => self::CURRENT,
        ])->assertSessionHasErrors('email');

        $this->assertSame('me@example.com', $user->fresh()?->email);
    }

    /**
     * The unique rule has to ignore the current user, or resubmitting the form
     * with an unchanged address would fail validation.
     */
    public function test_keeping_the_same_email_is_allowed(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patch('/account/email', [
            'email' => 'me@example.com',
            'current_password' => self::CURRENT,
        ])->assertSessionHasNoErrors();
    }

    public function test_updates_the_password(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patch('/account/password', [
            'current_password' => self::CURRENT,
            'password' => 'brand-new-password-2',
            'password_confirmation' => 'brand-new-password-2',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('brand-new-password-2', (string) $user->fresh()?->password));
    }

    /**
     * The controller uses forceFill to reach remember_token, which bypasses
     * mass-assignment guarding — but must not bypass the 'hashed' cast.
     */
    public function test_the_new_password_is_stored_hashed(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patch('/account/password', [
            'current_password' => self::CURRENT,
            'password' => 'brand-new-password-2',
            'password_confirmation' => 'brand-new-password-2',
        ]);

        $this->assertNotSame('brand-new-password-2', $user->fresh()?->password);
    }

    public function test_rejects_a_password_change_without_the_current_password(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patch('/account/password', [
            'current_password' => 'wrong-password-99',
            'password' => 'brand-new-password-2',
            'password_confirmation' => 'brand-new-password-2',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check(self::CURRENT, (string) $user->fresh()?->password));
    }

    public function test_rejects_a_weak_new_password(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patch('/account/password', [
            'current_password' => self::CURRENT,
            'password' => 'short-1',
            'password_confirmation' => 'short-1',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::CURRENT, (string) $user->fresh()?->password));
    }

    public function test_rejects_a_mismatched_confirmation(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patch('/account/password', [
            'current_password' => self::CURRENT,
            'password' => 'brand-new-password-2',
            'password_confirmation' => 'something-else-3',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::CURRENT, (string) $user->fresh()?->password));
    }

    /**
     * Revoking access elsewhere is the whole point of changing the password: a
     * lost phone holds a valid session for up to 30 days.
     */
    public function test_password_change_signs_out_other_sessions(): void
    {
        $user = $this->owner();

        DB::table('sessions')->insert([
            ['id' => 'other-device', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($user)->patch('/account/password', [
            'current_password' => self::CURRENT,
            'password' => 'brand-new-password-2',
            'password_confirmation' => 'brand-new-password-2',
        ]);

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
    }

    public function test_password_change_clears_the_remember_token(): void
    {
        $user = $this->owner();
        $user->forceFill(['remember_token' => 'a-long-lived-token'])->save();

        $this->actingAs($user)->patch('/account/password', [
            'current_password' => self::CURRENT,
            'password' => 'brand-new-password-2',
            'password_confirmation' => 'brand-new-password-2',
        ]);

        $this->assertNull($user->fresh()?->remember_token);
    }

    public function test_guests_cannot_touch_the_account(): void
    {
        $this->owner();

        $this->patch('/account/email', ['email' => 'x@example.com', 'current_password' => self::CURRENT])
            ->assertRedirect('/login');

        $this->patch('/account/password', [
            'current_password' => self::CURRENT,
            'password' => 'brand-new-password-2',
            'password_confirmation' => 'brand-new-password-2',
        ])->assertRedirect('/login');
    }
}
