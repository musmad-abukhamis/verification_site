<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['login' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    /**
     * Migrated nimcweb users know their username, not necessarily the address
     * they registered with -- so reset has to accept the same identifiers login
     * does. The link still goes only to the address on file.
     */
    public function test_reset_link_can_be_requested_with_a_username(): void
    {
        Notification::fake();

        $user = User::factory()->create(['username' => 'zaks']);

        $this->post('/forgot-password', ['login' => 'zaks']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_can_be_requested_with_a_phone_in_another_format(): void
    {
        Notification::fake();

        $user = User::factory()->create(['phone' => '+2348012345678']);

        $this->post('/forgot-password', ['login' => '08012345678']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_is_not_sent_for_an_unknown_identifier(): void
    {
        Notification::fake();

        User::factory()->create();

        $response = $this->post('/forgot-password', ['login' => 'no-such-account']);

        $response->assertSessionHasErrors('login');
        Notification::assertNothingSent();
    }

    /**
     * An ambiguous phone must not pick an account -- 15 migrated accounts share
     * a number once normalised, and mailing "the first match" would send one
     * person a link that resets someone else's password.
     */
    public function test_reset_link_is_not_sent_for_an_ambiguous_phone(): void
    {
        Notification::fake();

        User::factory()->create(['phone' => '08012345678']);
        User::factory()->create(['phone' => '+2348012345678']);

        $response = $this->post('/forgot-password', ['login' => '2348012345678']);

        $response->assertSessionHasErrors('login');
        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['login' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['login' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
