<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ServicePrice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * "API Access" — where a reseller collects the token they authenticate with.
 *
 * Nothing issued tokens before this: ApiTokenMiddleware read users.apitoken,
 * but no screen ever wrote it, so the reseller API could not actually be used
 * by anyone.
 *
 * Access follows role = API, which an admin sets from Admin > Users.
 */
class ApiAccessController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $enabled = $user->role === UserRole::API;

        return Inertia::render('ApiAccess/Index', [
            'enabled' => $enabled,
            'token' => $enabled ? $user->apitoken : null,
            'endpoint' => url('/api/v1'),
            // The caller's own rates, so the page doubles as a price list.
            'services' => $enabled ? $this->services($user) : [],
        ]);
    }

    /**
     * Issue a new token, invalidating the old one.
     */
    public function regenerate()
    {
        $user = Auth::user();

        if ($user->role !== UserRole::API) {
            return back()->withErrors(['token' => 'API access is not enabled on this account.']);
        }

        $user->update(['apitoken' => $this->newToken()]);

        return back()->with('success', 'A new API token has been issued. Update your integration — the previous token no longer works.');
    }

    private function newToken(): string
    {
        return 'sk_live_'.Str::random(48);
    }

    private function services($user): array
    {
        $services = [];

        foreach (ServicePrice::SERVICES as $service => $meta) {
            $price = ServicePrice::priceForUser($service, $user);

            if ($price !== null) {
                $services[] = [
                    'service' => $service,
                    'label' => $meta['label'],
                    'price' => $price,
                ];
            }
        }

        return $services;
    }
}
