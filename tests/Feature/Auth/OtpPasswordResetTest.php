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
        // Most cases exercise the local-code path; the Termii-generated path
        // has its own tests below.
        config()->set('services.termii.mode', 'plain');

        // Limits are per-identifier and per-IP, so they leak between tests.
        RateLimiter::clear('otp-send-ip:127.0.0.1');
        RateLimiter::clear('otp-verify-ip:127.0.0.1');
    }

    /**
     * Http::fake() appends stubs and the first match wins, so a second call
     * cannot override the first. Each test therefore registers its stubs once,
     * with any overrides placed ahead of the defaults.
     */
    private function fakeTermii(array $overrides = []): void
    {
        Http::fake($overrides + [
            '*/api/sms/send' => Http::response(['message_id' => 'abc123', 'message' => 'Successfully Sent']),
            '*/api/sms/otp/send' => Http::response(['pinId' => 'pin-123', 'smsStatus' => 'Message Sent']),
            '*/api/sms/otp/verify' => Http::response(['verified' => true, 'msisdn' => '+2348012345678']),
        ]);
    }

    public function test_sms_reset_screen_can_be_rendered(): void
    {
        $this->fakeTermii();
        $this->get('/reset-password-sms')->assertStatus(200);
    }

    public function test_code_is_sent_and_stored_hashed(): void
    {
        $this->fakeTermii();
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
        $this->fakeTermii();
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
        $this->fakeTermii();
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
        $this->fakeTermii();
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
        $this->fakeTermii();
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
        $this->fakeTermii();
        User::factory()->create(['username' => 'zaks', 'phone' => '08012345678']);

        $this->post('/reset-password-sms/send', ['login' => 'not-a-real-account'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sent', true);

        Http::assertNothingSent();
    }

    /**
     * Plain SMS is not activated on this Termii account ("Country Inactive"),
     * so production runs in otp mode: Termii generates the code and we hold
     * only the pin_id needed to verify it.
     */
    public function test_otp_mode_stores_the_termii_pin_id_and_verifies_remotely(): void
    {
        $this->fakeTermii();
        config()->set('services.termii.mode', 'otp');

        $user = User::factory()->create(['username' => 'zaks', 'phone' => '08012345678']);

        $this->post('/reset-password-sms/send', ['login' => 'zaks'])
            ->assertSessionHasNoErrors();

        $otp = Otp::where('userId', $user->id)->first();
        $this->assertSame('termii:pin-123', $otp->code);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/sms/otp/send')
            && $request['pin_length'] === 6
            && str_contains($request['message_text'], '<1234>'));

        $this->post('/reset-password-sms', [
            'login' => 'zaks',
            'code' => '654321',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertNull(Otp::where('userId', $user->id)->first());
    }

    public function test_otp_mode_rejects_a_code_termii_does_not_verify(): void
    {
        config()->set('services.termii.mode', 'otp');
        $this->fakeTermii([
            '*/api/sms/otp/verify' => Http::response(['verified' => false], 400),
        ]);

        $user = User::factory()->create(['username' => 'zaks', 'phone' => '08012345678']);

        $this->post('/reset-password-sms/send', ['login' => 'zaks']);

        $this->post('/reset-password-sms', [
            'login' => 'zaks',
            'code' => '000000',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('code');

        $this->assertFalse(Hash::check('new-password-123', $user->fresh()->password));
    }

    /**
     * A rejected send must not replace a code the user already has. Writing the
     * row first would lock them out with a code that was never delivered.
     */
    public function test_a_failed_send_leaves_any_existing_code_intact(): void
    {
        $this->fakeTermii([
            '*/api/sms/send' => Http::response([
                'code' => 400,
                'message' => 'Country Inactive. Contact Administrator to activate country.',
            ], 400),
        ]);

        $user = User::factory()->create(['username' => 'zaks', 'phone' => '08012345678']);

        Otp::create([
            'userId' => $user->id,
            'code' => Hash::make('123456'),
            'expiresAt' => Carbon::now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $this->post('/reset-password-sms/send', ['login' => 'zaks'])
            ->assertSessionHasNoErrors();

        // The code they already received still works.
        $this->post('/reset-password-sms', [
            'login' => 'zaks',
            'code' => '123456',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'));
    }

    public function test_send_is_rate_limited_per_identifier(): void
    {
        $this->fakeTermii();
        User::factory()->create(['username' => 'zaks', 'phone' => '08012345678']);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/reset-password-sms/send', ['login' => 'zaks'])
                ->assertSessionHasNoErrors();
        }

        $this->post('/reset-password-sms/send', ['login' => 'zaks'])
            ->assertSessionHasErrors('login');
    }
}
