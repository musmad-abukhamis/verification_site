<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * nimcweb hashed with bcryptjs, which emits $2a$/$2b$. password_verify()
     * accepts those, but password_get_info() reports them as "unknown", so
     * Laravel's bcrypt algorithm check throws rather than returning false --
     * a 500 on the login page for every migrated account.
     *
     * config/hashing.php disables that check. This test fails if it comes back.
     */
    #[DataProvider('bcryptjsPrefixes')]
    public function test_migrated_bcryptjs_hashes_can_authenticate(string $prefix): void
    {
        $user = $this->userWithRawHash('migrated', $prefix, 'migrated-secret');

        $this->post('/login', [
            'login' => 'migrated',
            'password' => 'migrated-secret',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_migrated_bcryptjs_hash_rejects_a_wrong_password(): void
    {
        $this->userWithRawHash('migrated', '$2a$', 'migrated-secret');

        $this->post('/login', [
            'login' => 'migrated',
            'password' => 'not-the-password',
        ]);

        $this->assertGuest();
    }

    /**
     * Writes the hash with the query builder, not the model.
     *
     * User casts password as "hashed", and that cast decides whether a value
     * is already hashed with the same password_get_info() check that reports
     * $2a$/$2b$ as "unknown" -- so assigning one through Eloquent hashes it a
     * second time. The migration inserted these rows with raw COPY, so this
     * reproduces what is actually in the database.
     */
    private function userWithRawHash(string $username, string $prefix, string $password): User
    {
        $salt = '$2a$10$'.substr(str_replace('+', '.', base64_encode(random_bytes(16))), 0, 22);
        $hash = $prefix.substr(crypt($password, $salt), 4);

        $user = User::factory()->create(['username' => $username]);

        DB::table('users')->where('id', $user->id)->update(['password' => $hash]);

        return $user->refresh();
    }

    public static function bcryptjsPrefixes(): array
    {
        return [
            '$2a$ (1546 accounts)' => ['$2a$'],
            '$2b$ (713 accounts)' => ['$2b$'],
            '$2y$ (native)' => ['$2y$'],
        ];
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
