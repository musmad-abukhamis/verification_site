<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $login = $this->input('login');
        
        // Normalize phone number: remove spaces
        if ($login && !str_contains($login, '@')) {
            $login = preg_replace('/\s+/', '', $login);
            $this->merge(['login' => $login]);
        }
    }

    /**
     * Determine if the login input is an email.
     */
    public function isEmail(): bool
    {
        return str_contains($this->input('login'), '@');
    }

    /**
     * Resolve the account being logged into, by email, username or phone.
     *
     * Users migrated from nimcweb signed in with their USERNAME, so username
     * has to be accepted here or none of them can get in -- they see
     * "credentials do not match" and report it as a broken password.
     *
     * Phone is matched on the last 10 digits because the same person may be
     * stored as 08012345678 or +2348012345678; a byte-exact comparison treats
     * those as different people. That normalisation is ambiguous for the 15
     * accounts sharing a number, so it is only honoured when it identifies
     * exactly one account -- an exact match is always preferred.
     */
    protected function resolveUser(string $login): ?User
    {
        if (str_contains($login, '@')) {
            return User::whereRaw('lower(email) = ?', [Str::lower($login)])->first();
        }

        $user = User::whereRaw('lower(username) = ?', [Str::lower($login)])->first()
            ?? User::where('phone', $login)->first();

        if ($user) {
            return $user;
        }

        $digits = preg_replace('/\D/', '', $login);

        if (strlen($digits) < 10) {
            return null;
        }

        // Build the equivalent formats in PHP rather than normalising the
        // column in SQL: it keeps the phone index usable, and avoids
        // regexp_replace/right(), which are Postgres-only and would break the
        // SQLite-backed test suite.
        $local = substr($digits, -10);
        $candidates = ['0'.$local, '234'.$local, '+234'.$local, $local];

        $matches = User::whereIn('phone', $candidates)->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = $this->input('login');
        $password = $this->input('password');

        // Resolve the account first, then authenticate by id, so the password
        // check itself stays Auth::attempt's job (hashing, rehashing, events).
        $user = $this->resolveUser($login);

        $credentials = [
            'id' => $user?->id ?? '',
            'password' => $password,
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
