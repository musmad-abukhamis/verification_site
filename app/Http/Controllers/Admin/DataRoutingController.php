<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataSetting;
use App\Models\NetworkPrefix;
use App\Models\NetworkVendorMapping;
use App\Models\Plan;
use App\Models\Vendor;
use App\Models\VendorRoute;
use App\Services\DataCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DataRoutingController extends Controller
{
    private const NETWORKS = ['mtn', 'airtel', 'glo', '9mobile'];

    public function index()
    {
        $types = Plan::query()->distinct()->orderBy('type')->pluck('type')->filter()->values()->all();

        $routes = VendorRoute::orderBy('position')->get()
            ->groupBy(fn (VendorRoute $r) => $r->network.'|'.$r->type)
            ->map(fn ($rows) => $rows->pluck('vendor_id')->all());

        $codes = NetworkVendorMapping::all()
            ->groupBy('network')
            ->map(fn ($rows) => $rows->pluck('external_network_code', 'vendor_id'));

        return Inertia::render('Admin/Data/Routing', [
            'networks' => self::NETWORKS,
            'types' => $types,
            'vendors' => Vendor::orderBy('priority')->get(['id', 'name', 'is_active'])
                ->map(fn (Vendor $v) => ['id' => $v->id, 'name' => $v->name, 'is_active' => $v->is_active]),
            'routes' => $routes,
            'networkCodes' => $codes,
            'settings' => [
                'failover_enabled' => DataSetting::bool('failover_enabled'),
                'failover_max_attempts' => DataSetting::int('failover_max_attempts'),
                'reconcile_cutoff_minutes' => DataSetting::int('reconcile_cutoff_minutes', 120),
                'requery_interval_minutes' => DataSetting::int('requery_interval_minutes', 5),
            ],
            'prefixes' => NetworkPrefix::map(),
        ]);
    }

    public function updateRoutes(Request $request)
    {
        $validated = $request->validate([
            'routes' => ['array'],
            'routes.*.network' => ['required', 'string'],
            'routes.*.type' => ['required', 'string'],
            'routes.*.vendor_ids' => ['array'],
            'routes.*.vendor_ids.*' => ['string', 'exists:vendors,id'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['routes'] ?? [] as $route) {
                VendorRoute::where('network', $route['network'])->where('type', $route['type'])->delete();

                foreach (array_values($route['vendor_ids'] ?? []) as $i => $vendorId) {
                    VendorRoute::create([
                        'network' => $route['network'],
                        'type' => $route['type'],
                        'vendor_id' => $vendorId,
                        'position' => $i + 1,
                    ]);
                }
            }
        });

        return back()->with('success', 'Routing updated.');
    }

    public function updateNetworkCodes(Request $request)
    {
        $validated = $request->validate([
            'codes' => ['array'],
            'codes.*.network' => ['required', 'string'],
            'codes.*.vendor_id' => ['required', 'string', 'exists:vendors,id'],
            'codes.*.external_network_code' => ['nullable', 'string', 'max:50'],
        ]);

        foreach ($validated['codes'] ?? [] as $code) {
            $value = trim((string) ($code['external_network_code'] ?? ''));
            if ($value === '') {
                NetworkVendorMapping::where('network', $code['network'])->where('vendor_id', $code['vendor_id'])->delete();

                continue;
            }
            NetworkVendorMapping::updateOrCreate(
                ['network' => $code['network'], 'vendor_id' => $code['vendor_id']],
                ['external_network_code' => $value],
            );
        }

        return back()->with('success', 'Network codes updated.');
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'failover_enabled' => ['boolean'],
            'failover_max_attempts' => ['integer', 'min:0', 'max:20'],
            'reconcile_cutoff_minutes' => ['integer', 'min:5', 'max:1440'],
            'requery_interval_minutes' => ['integer', 'min:1', 'max:120'],
        ]);

        DataSetting::put('failover_enabled', $request->boolean('failover_enabled'));
        DataSetting::put('failover_max_attempts', (int) $validated['failover_max_attempts']);
        DataSetting::put('reconcile_cutoff_minutes', (int) $validated['reconcile_cutoff_minutes']);
        DataSetting::put('requery_interval_minutes', (int) $validated['requery_interval_minutes']);

        return back()->with('success', 'Settings saved.');
    }

    public function addPrefix(Request $request)
    {
        $validated = $request->validate([
            'network' => ['required', 'string', 'max:50'],
            'prefix' => ['required', 'string', 'regex:/^\d{3,6}$/'],
        ]);

        NetworkPrefix::updateOrCreate([
            'network' => strtolower($validated['network']),
            'prefix' => $validated['prefix'],
        ]);
        DataCache::flush();

        return back()->with('success', 'Prefix added.');
    }

    public function removePrefix(Request $request)
    {
        $validated = $request->validate([
            'network' => ['required', 'string'],
            'prefix' => ['required', 'string'],
        ]);

        NetworkPrefix::where('network', strtolower($validated['network']))
            ->where('prefix', $validated['prefix'])
            ->delete();
        DataCache::flush();

        return back()->with('success', 'Prefix removed.');
    }
}
