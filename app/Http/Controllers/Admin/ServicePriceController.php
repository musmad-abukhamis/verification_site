<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ManagesServicePrices;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin > Service Prices — the NIN services and slip downloads.
 *
 * Every service has one base price plus optional per-role overrides, stored one
 * row per (service, role) in service_prices. That replaced the single-row
 * ninServicePrices / verifyapiconfiq layout, which had exactly one price per
 * service and so could not express "agents pay less".
 */
class ServicePriceController extends Controller
{
    use ManagesServicePrices;

    private const GROUPS = ['verification', 'slip'];

    public function index()
    {
        return Inertia::render('Admin/ServicePrices/Index', [
            'services' => $this->servicesPayload(self::GROUPS),
            'roles' => $this->overridableRoles(),
        ]);
    }

    public function update(Request $request, string $service)
    {
        return $this->saveService($request, $service, self::GROUPS);
    }
}
