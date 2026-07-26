<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9_.]+$/',
                Rule::unique(User::class),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+?[0-9]+$/',
                Rule::unique(User::class),
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Normalize phone number
        $phone = preg_replace('/\s+/', '', $request->phone);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'phone' => $phone,
            'password' => Hash::make($request->password),
        ]);

        // The Registered listener sends the email-verification notification
        // synchronously, so a mail failure (bad Brevo key, unverified sender,
        // API unreachable) would 500 the request *after* the account row is
        // already committed -- the user sees an error page but their account
        // exists. Verification is not required to reach the dashboard, so a
        // failure here must not block sign-in; log it and carry on. The user
        // can request a fresh link from the verification prompt.
        try {
            event(new Registered($user));
        } catch (TransportExceptionInterface $e) {
            Log::error('Verification email failed to send on registration', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
