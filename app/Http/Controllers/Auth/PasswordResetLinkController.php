<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => 'required|string',
        ]);

        // Accept username or phone as well as email, matching the login form:
        // accounts migrated from nimcweb signed in with a username there and
        // often do not recall the address they registered with. The link is
        // still only ever sent to the address on file, so nothing is disclosed
        // to whoever submitted the form.
        $user = User::findByIdentifier($request->input('login'));

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => [trans(Password::INVALID_USER)],
            ]);
        }

        // Mail delivery is genuinely unreliable here -- this app has run with
        // MAIL_HOST unset, and a missing/unreachable SMTP host makes Symfony
        // throw mid-request. That used to reach the user as a 500 with no way
        // forward; point them at the SMS route instead.
        if (! $this->mailerConfigured()) {
            Log::error('Password reset link not sent: mail transport is not configured');

            throw ValidationException::withMessages([
                'login' => [$this->undeliverableMessage()],
            ]);
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (TransportExceptionInterface $e) {
            Log::error('Password reset link failed to send', ['error' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'login' => [$this->undeliverableMessage()],
            ]);
        }

        if ($status == Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'login' => [trans($status)],
        ]);
    }

    /**
     * An SMTP mailer with no host cannot deliver; it throws on connect.
     */
    private function mailerConfigured(): bool
    {
        $mailer = config('mail.default');

        if (in_array($mailer, ['smtp', 'ses', 'postmark', 'resend'], true)) {
            return filled(config("mail.mailers.{$mailer}.host"))
                || filled(config("mail.mailers.{$mailer}.token"))
                || filled(config("mail.mailers.{$mailer}.key"));
        }

        return true;
    }

    private function undeliverableMessage(): string
    {
        return 'We could not send the email right now. Please use the "Get a code by SMS" option instead.';
    }
}
