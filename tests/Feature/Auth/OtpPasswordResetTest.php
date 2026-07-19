<?php

namespace Tests\Feature\Auth;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OtpPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.termii.key', 'test-key');

        Http::fake([
            '*termii*' => Http::response(['message_id' => 'abc123', 'message' => 'Successfully Sent']),
        ]);

        // Limits are per-identifier and per-IP, so they leak between tests.
        RateLimiter::clear('otp-send-ip:127.0.0.1');
        RateLimiter::clear('otp-verify-ip:127.0.0.1');
    }

    public function test_sms_reset_screen_can_be_rendered(): void
    {
        $this->get('/reset-password-sms')->assertStatus(200);
    }

    public function test_code_is_sent_and_stored_hashed(): void
    {
        $user = User::factory()->create(['username' => 'zaks', 'phone' => '08012345678']);

        $this->post('/reset-password-sms/send', ['login' => 'zaks'])
            ->assertSessionHasNoErrors();

        $otp = Otp::where('userId', $user->id)->first();

        $this->assertNotNull($otp);
        // The stored value must not be the code itself -- the table is not a receipt.
        $this->assertStringStartsWith('$2y$', $otp->code);
        $this->assertSame(0, $otp->attempts);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/sms/send')
            && $request['to'] === '+2348012345678');
    }

    public function test_password_can_be_reset_with_a_valid_code(): void
    {
        $user = User::factory()->create(['username' => 'zaks', 'phone' => '08012345678']);

        Otp::create([
            'userId' => $user->id,
            'code' => Hash::make('123456'),
            'expiresAt' => Carbon::now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $this->post('/reset-password-sms', [
            'login' => 'zaks',
            'code' => '123456',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        // One code, one reset.
        $this->assertNull(Otp::where('userId', $user->id)->first());
    }

    public function test_expired_code_is_rejected_and_discarded(): void
    {
        $user = User::factory()->create(['username' => 'zaks', 'phone' => '08012345678']);

        Otp::create([
            'userId' => $user->id,
            'code' => Hash::make('123456'),
            'expiresAt' => Carbon::now()->subMinute(),
            'attempts' => 0,
        ]);

        $this->post('/reset-password-sms', [
            'login' => 'zaks',
            'code' => '123456',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('code');

        $this->assertFalse(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertNull(Otp::where('userId', $user->id)->first());
    }

    public function test_code_is_destroyed_after_five_wrong_guesses(): void
    {
        $user = User::factory()->create(['username' => 'zaks', 'phone' => '08012345678']);

        Otp::create([
            'userId' => $user->id,
            'code' => Hash::make('123456'),
            'expiresAt' => Carbon::now()->addMinutes(10),
            'attempts' => 0,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/reset-password-sms', [
                'login' => 'zaks',
                'code' => '000000',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertSessionHasErrors('code');
        }

        $this->assertNull(Otp::where('userId', $user->id)->first());

        // The real code is now useless too.
        $this->post('/reset-password-sms', [
            'login' => 'zaks',
            'code' => '123456',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('code');

        $this->assertFalse(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_requesting_a_new_code_invalidates_the_previous_one(): void
    {
        $user = User::factory()->create(['username' => 'zaks', 'phone' => '08012345678']);

        Otp::create([
            'userId' => $user->id,
            'code' => Hash::make('111111'),
            'expiresAt' => Carbon::now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $this->post('/reset-password-sms/send', ['login' => 'zaks']);

        $this->post('/reset-password-sms', [
            'login' => 'zaks',
            'code' => '111111',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('code');
    }

    /**
     * Sending costs money per message, so the endpoint must not confirm whether
     * an identifier belongs to a real account.
     */
    public function test_unknown_identifier_gets_the_same_answer_and_sends_nothing(): void
    {
        User::factory()->create(['username' => 'zaks', 'phone' => '08012345678']);

        $this->post('/reset-password-sms/send', ['login' => 'not-a-real-account'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sent', true);

        Http::assertNothingSent();
    }

    public function test_send_is_rate_limited_per_identifier(): void
    {
        User::factory()->create(['username' => 'zaks', 'phone' => '08012345678']);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/reset-password-sms/send', ['login' => 'zaks'])
                ->assertSessionHasNoErrors();
        }

        $this->post('/reset-password-sms/send', ['login' => 'zaks'])
            ->assertSessionHasErrors('login');
    }
}
