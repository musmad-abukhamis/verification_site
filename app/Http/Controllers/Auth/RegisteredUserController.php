<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

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

        // Deliberately NOT dispatching Illuminate\Auth\Events\Registered here.
        //
        // User implements MustVerifyEmail, so that event's listener sends the
        // verification notification synchronously -- and a mail failure then
        // 500s the request *after* the account row is committed, leaving the
        // user staring at an error page for an account that does exist.
        //
        // Verification is not required to reach the dashboard (see the note on
        // the dashboard route), so the email buys nothing at signup. Users who
        // need a link can request one from the verification prompt, which goes
        // through verification.send.
        //
        // To re-enable, restore `event(new Registered($user));` -- but wrap it
        // in a try/catch on TransportExceptionInterface, or you reintroduce the
        // 500, and make sure BREVO_API_KEY is a valid v3 API key first.

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
