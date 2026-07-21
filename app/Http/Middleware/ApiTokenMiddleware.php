<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates API-role users by their `users.apitoken` string (the app has no
 * Sanctum personal-access-token infrastructure). Accepts the token via a Bearer
 * Authorization header or an `api_token` field, and requires role = API.
 */
class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->input('api_token');

        if (! $token) {
            return response()->json(['status' => 'error', 'message' => 'API token required'], 401);
        }

        $user = User::where('apitoken', $token)->first();

        if (! $user || $user->role !== UserRole::API) {
            return response()->json(['status' => 'error', 'message' => 'Invalid API token'], 401);
        }

        // Bind the resolved user so controllers can use $request->user()...
        $request->setUserResolver(fn () => $user);

        // ...and log them into the guard for this request, so everything that
        // reads Auth::user() -- role-based pricing especially -- sees the token
        // holder. Without it those callers silently price for a guest.
        Auth::setUser($user);

        return $next($request);
    }
}
