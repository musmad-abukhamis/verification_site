<?php

namespace App\Http\Controllers;

use App\Services\DataCache;
use App\Support\DataRequestNormalizer;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Data Pricing — the public plan list.
 *
 * Priced for whoever is looking: a signed-in agent or API reseller sees their
 * own rate, a visitor sees the retail price. That is the same resolution the
 * purchase itself uses, so the number on this page is the number they pay.
 */
class DataPricingController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $plans = collect(DataCache::catalogForRole($user?->role))
            ->filter(fn (array $plan) => $plan['available'])
            ->map(fn (array $plan) => [
                'plan_id' => $plan['id'],
                'network' => $plan['network'],
                'network_id' => array_search($plan['network'], DataRequestNormalizer::NETWORK_IDS, true) ?: null,
                'name' => $plan['name'],
                'type' => $plan['type'],
                'price' => $plan['price'],
                'validity' => $plan['validity'],
            ])
            ->sortBy(['network', 'type', 'price'])
            ->values();

        return Inertia::render('DataPricing/Index', [
            'plans' => $plans,
            // Drives the network sections, so a network with no plans simply
            // does not appear rather than rendering an empty table.
            'networks' => $plans->pluck('network')->unique()->values(),
            'role' => $user?->role?->value,
            'authenticated' => (bool) $user,
        ]);
    }
}
