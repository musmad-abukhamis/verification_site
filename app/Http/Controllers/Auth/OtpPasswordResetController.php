<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\Services\Termii;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Password reset by SMS code, for accounts migrated from nimcweb.
 *
 * The emailed reset link assumes the user still reads the address they signed
 * up with; for these agents that is often untrue, while the phone number is
 * current. Delivery is Termii SMS.
 *
 * The code is stored HASHED. The OTP row is not a receipt -- anyone reading the
 * table would otherwise be able to complete a reset for any account that has
 * one outstanding.
 */
class OtpPasswordResetController extends Controller
{
    /** Codes are short, so they must be short-lived. */
    private const TTL_MINUTES = 10;

    /** Wrong guesses allowed before the code is destroyed. */
    private const MAX_ATTEMPTS = 5;

    /** Marks an OTP row as holding a Termii pin_id rather than a local hash. */
    private const REMOTE_PREFIX = 'termii:';

    public function create(): Response
    {
        return Inertia::render('Auth/ResetPasswordOtp', [
            'status' => session('status'),
            'sent' => session('sent', false),
            'login' => session('login'),
        ]);
    }

    /**
     * Issue a code and text it to the number on the account.
     */
    public function send(Request $request, Termii $termii): RedirectResponse
    {
        $request->validate(['login' => 'required|string']);

        $login = $request->input('login');

        // Two limits, because each send costs money: one per identifier so a
        // single account cannot be spammed, one per IP so a script cannot walk
        // the user list and burn the SMS balance.
        $this->hitLimit('otp-send:'.Str::lower($login), 3, 600);
        $this->hitLimit('otp-send-ip:'.$request->ip(), 10, 600);

        $user = User::findByIdentifier($login);

        if ($user && filled($user->phone)) {
            $this->issueCode($user, $termii);
        }

        // Deliberately the same answer whether or not the account exists: this
        // endpoint spends money per call, so confirming which identifiers are
        // real would be worth harvesting.
        return back()
            ->with('sent', true)
            ->with('login', $login)
            ->with('status', 'If that account exists, we sent a 6-digit code to the phone number registered on it.');
    }

    /**
     * Issue a code by whichever Termii product is available.
     *
     * The stored OTP row is only written after Termii accepts the message.
     * Writing first would replace a working code with one the user never
     * received, locking them out until it expires.
     *
     * In "otp" mode the code lives at Termii and we hold its pin_id, marked
     * with a prefix so reset() knows how to check it. The prefix also keeps
     * codes issued before a mode switch verifiable.
     */
    private function issueCode(User $user, Termii $termii): void
    {
        $expiresAt = Carbon::now()->addMinutes(self::TTL_MINUTES);

        $message = 'Your '.config('app.name').' password reset code is :code. '
            .'It expires in '.self::TTL_MINUTES.' minutes. '
            .'If you did not request this, ignore this message.';

        if ($termii->usesRemoteOtp()) {
            $pinId = $termii->sendOtp(
                $user->phone,
                $message,
                length: 6,
                ttlMinutes: self::TTL_MINUTES,
                attempts: self::MAX_ATTEMPTS,
            );

            if (! $pinId) {
                return;
            }

            $this->storeCode($user, self::REMOTE_PREFIX.$pinId, $expiresAt);

            return;
        }

        $code = (string) random_int(100000, 999999);

        if (! $termii->send($user->phone, str_replace(':code', $code, $message))) {
            return;
        }

        $this->storeCode($user, Hash::make($code), $expiresAt);
    }

    /**
     * userId is unique on OTP, so this replaces any outstanding code --
     * requesting a new one invalidates the old, as it should.
     */
    private function storeCode(User $user, string $value, Carbon $expiresAt): void
    {
        Otp::updateOrCreate(
            ['userId' => $user->id],
            ['code' => $value, 'expiresAt' => $expiresAt, 'attempts' => 0],
        );
    }

    /**
     * Verify the code and set the new password.
     */
    public function reset(Request $request, Termii $termii): RedirectResponse
    {
        $request->validate([
            'login' => 'required|string',
            'code' => 'required|string',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $this->hitLimit('otp-verify-ip:'.$request->ip(), 20, 600);

        $user = User::findByIdentifier($request->input('login'));
        $otp = $user?->otp;

        if (! $otp || Carbon::now()->greaterThan($otp->expiresAt)) {
            $otp?->delete();

            throw ValidationException::withMessages([
                'code' => ['That code has expired. Please request a new one.'],
            ]);
        }

        if (! $this->codeMatches($otp, $request->input('code'), $termii)) {
            $otp->increment('attempts');

            if ($otp->attempts >= self::MAX_ATTEMPTS) {
                $otp->delete();

                throw ValidationException::withMessages([
                    'code' => ['Too many incorrect attempts. Please request a new code.'],
                ]);
            }

            throw ValidationException::withMessages([
                'code' => ['That code is not correct.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->input('password')),
            'remember_token' => Str::random(60),
        ])->save();

        // One code, one reset.
        $otp->delete();

        return redirect()->route('login')
            ->with('status', 'Your password has been reset. Please sign in.');
    }

    /**
     * A locally-issued code is a hash we check ourselves; a Termii-issued one
     * has to go back to their API.
     */
    private function codeMatches(Otp $otp, string $submitted, Termii $termii): bool
    {
        if (str_starts_with($otp->code, self::REMOTE_PREFIX)) {
            return $termii->verifyOtp(substr($otp->code, strlen(self::REMOTE_PREFIX)), $submitted);
        }

        return Hash::check($submitted, $otp->code);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function hitLimit(string $key, int $max, int $decaySeconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'login' => ["Too many requests. Please try again in ".ceil($seconds / 60)." minute(s)."],
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }
}
