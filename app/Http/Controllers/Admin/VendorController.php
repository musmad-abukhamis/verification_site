<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VendorRequest;
use App\Models\Vendor;
use App\Services\DataCache;
use Inertia\Inertia;

class VendorController extends Controller
{
    private const DRIVERS = [
        ['value' => 'token_style_a', 'label' => 'Token style A ({network,phone,bypass,data_plan})'],
        ['value' => 'token_style_b', 'label' => 'Token style B ({network,mobile_number,plan,Ported_number})'],
        ['value' => 'oauth', 'label' => 'OAuth (client credentials → token)'],
    ];

    /** Fields whose values are masked in the UI, per driver. */
    private const SECRET_FIELDS = ['key', 'client_secret'];

    public function index()
    {
        $vendors = Vendor::withCount(['routes', 'planMappings'])
            ->orderBy('priority')
            ->get()
            ->map(fn (Vendor $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'base_url' => $v->base_url,
                'driver' => $v->driver,
                'is_active' => $v->is_active,
                'priority' => $v->priority,
                'routes_count' => $v->routes_count,
                'plan_mappings_count' => $v->plan_mappings_count,
            ]);

        return Inertia::render('Admin/Vendors/Index', [
            'vendors' => $vendors,
            'drivers' => self::DRIVERS,
        ]);
    }

    public function store(VendorRequest $request)
    {
        $data = $request->validated();
        $data['credentials'] = array_filter($request->input('credentials', []), fn ($v) => $v !== null && $v !== '');

        Vendor::create($data);
        DataCache::flush();

        return redirect()->route('admin.vendors.index')->with('success', 'Vendor created.');
    }

    public function update(VendorRequest $request, Vendor $vendor)
    {
        $data = $request->validated();

        // Merge credentials: only overwrite fields the admin actually typed, so
        // masked secrets left untouched keep their stored value.
        $existing = (array) $vendor->credentials;
        foreach ($request->input('credentials', []) as $key => $value) {
            if ($value !== null && $value !== '') {
                $existing[$key] = $value;
            }
        }
        $data['credentials'] = $existing;

        $vendor->update($data);
        DataCache::flush();

        return redirect()->route('admin.vendors.index')->with('success', 'Vendor updated.');
    }

    public function toggle(Vendor $vendor)
    {
        $vendor->update(['is_active' => ! $vendor->is_active]);
        DataCache::flush();

        return back()->with('success', 'Vendor '.($vendor->is_active ? 'activated' : 'deactivated').'.');
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete(); // cascades mappings/routes
        DataCache::flush();

        return redirect()->route('admin.vendors.index')->with('success', 'Vendor deleted.');
    }
}
