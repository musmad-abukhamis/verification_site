<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ManagesServicePrices;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * BVN service prices — admin.
 *
 * Was a single form over the one-column-per-service `bvnserviceprices` row.
 * The BVN services now live in service_prices alongside the NIN ones, so they
 * get the same per-role pricing; this page is the same editor scoped to the
 * BVN groups.
 */
class BvnPriceController extends Controller
{
    use ManagesServicePrices;

    private const GROUPS = ['bvn_modification', 'bvn_search', 'bvn_other'];

    public function index()
    {
        return Inertia::render('Admin/BvnPrices/Index', [
            'services' => $this->servicesPayload(self::GROUPS),
            'roles' => $this->overridableRoles(),
        ]);
    }

    public function update(Request $request, string $service)
    {
        return $this->saveService($request, $service, self::GROUPS);
    }
}
