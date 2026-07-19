<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    /**
     * Users migrated from nimcweb signed in with their username, so login has
     * to accept it -- otherwise every migrated account reports a dead password.
     */
    public function test_users_can_authenticate_using_their_username(): void
    {
        $user = User::factory()->create(['username' => 'zaks']);

        $this->post('/login', [
            'login' => 'zaks',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_username_login_is_case_insensitive(): void
    {
        $user = User::factory()->create(['username' => 'zaks']);

        $this->post('/login', ['login' => 'ZAKS', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_authenticate_using_phone_in_another_format(): void
    {
        $user = User::factory()->create(['phone' => '+2348012345678']);

        $this->post('/login', [
            'login' => '08012345678',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    /**
     * 15 source accounts share a phone number once normalised. An ambiguous
     * number must not log anyone in -- picking "the first match" would hand
     * one person another person's account.
     */
    public function test_ambiguous_phone_does_not_authenticate(): void
    {
        User::factory()->create(['phone' => '08012345678']);
        User::factory()->create(['phone' => '+2348012345678']);

        $this->post('/login', [
            'login' => '2348012345678',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_unknown_login_does_not_authenticate(): void
    {
        User::factory()->create();

        $this->post('/login', [
            'login' => 'nobody-with-this-name',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
