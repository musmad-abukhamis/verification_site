<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ServicePrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account endpoints for API resellers: what they can spend and what it costs.
 *
 * The price list is generated from the caller's own role, so an integrator sees
 * the rates they will actually be billed rather than a public rate card.
 */
class AccountController extends Controller
{
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'balance' => (float) $user->balance,
                'currency' => 'NGN',
            ],
        ]);
    }

    public function services(Request $request): JsonResponse
    {
        $user = $request->user();

        $services = [];

        foreach (ServicePrice::SERVICES as $service => $meta) {
            $price = ServicePrice::priceForUser($service, $user);

            // Unavailable services are listed with a null price rather than
            // hidden, so an integrator can tell "switched off" from "typo".
            $services[] = [
                'service' => $service,
                'label' => $meta['label'],
                'group' => $meta['group'],
                'price' => $price,
                'available' => $price !== null,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'role' => $user->role?->value,
                'currency' => 'NGN',
                'services' => $services,
            ],
        ]);
    }
}
