<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorApiRequest;
use App\Models\VendorApi;
use App\Models\VendorSelection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class VendorController extends Controller
{
    /** Networks that carry a vendor selection row. */
    private array $networks = ['MTN', 'GLO', 'AIRTEL', '9MOBILE'];

    /** The service-type columns on the vendorselection row. */
    private array $types = ['SME', 'SME2', 'CORPORATE_GIFTING', 'CORPORATE_GIFTING2', 'DATASHARE'];

    private function vendorList(): array
    {
        return [
            ['id' => 1, 'name' => 'VTpass'],
            ['id' => 2, 'name' => 'ClubKonnect'],
            ['id' => 3, 'name' => 'Monnify'],
            ['id' => 4, 'name' => 'Flutterwave'],
            ['id' => 5, 'name' => 'Paystack'],
        ];
    }

    /**
     * Flatten the per-network vendorselection rows into {network,type,vendor_number}.
     */
    private function activeVendorMatrix(): array
    {
        $matrix = [];

        foreach ($this->networks as $network) {
            $selection = VendorSelection::firstOrCreate(['id' => $network]);

            foreach ($this->types as $type) {
                $matrix[] = [
                    'network' => $network,
                    'type' => $type,
                    'vendor_number' => (int) ($selection->{$type} ?? 1),
                ];
            }
        }

        return $matrix;
    }

    /**
     * Display a listing of vendors
     */
    public function index()
    {
        $vendors = [
            ['id' => 1, 'name' => 'VTpass', 'status' => 'active'],
            ['id' => 2, 'name' => 'ClubKonnect', 'status' => 'active'],
            ['id' => 3, 'name' => 'Monnify', 'status' => 'inactive'],
            ['id' => 4, 'name' => 'Flutterwave', 'status' => 'inactive'],
            ['id' => 5, 'name' => 'Paystack', 'status' => 'inactive'],
        ];

        return Inertia::render('Admin/Vendors/Index', [
            'vendors' => $vendors,
            'activeVendors' => $this->activeVendorMatrix(),
        ]);
    }

    /**
     * Show vendor API configuration
     */
    public function apiConfig()
    {
        $vendorApi = VendorApi::first();

        return Inertia::render('Admin/Vendors/ApiConfig', [
            'vendorApi' => $vendorApi ?? new VendorApi(),
        ]);
    }

    /**
     * Update vendor API configuration
     */
    public function updateApiConfig(VendorApiRequest $request)
    {
        $vendorApi = VendorApi::first() ?? new VendorApi();

        $vendorApi->fill($request->validated());
        $vendorApi->save();

        return response()->json([
            'message' => 'Vendor API configuration updated successfully!',
            'data' => $vendorApi,
        ], 200);
    }

    /**
     * Show active vendors configuration
     */
    public function activeVendors()
    {
        return Inertia::render('Admin/Vendors/ActiveVendors', [
            'activeVendors' => $this->activeVendorMatrix(),
            'networks' => $this->networks,
            'types' => $this->types,
            'vendors' => $this->vendorList(),
        ]);
    }

    /**
     * Update active vendors configuration
     */
    public function updateActiveVendors(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'configurations' => 'required|array',
            'configurations.*.network' => 'required|string',
            'configurations.*.type' => 'required|string',
            'configurations.*.vendor_number' => 'required|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        foreach ($request->configurations as $config) {
            $type = strtoupper(str_replace(' ', '_', $config['type']));

            if (! in_array($type, $this->types, true)) {
                continue;
            }

            $selection = VendorSelection::firstOrCreate(['id' => strtoupper($config['network'])]);
            $selection->update([$type => (string) $config['vendor_number']]);
        }

        return back()->with('success', 'Active vendors configuration updated successfully.');
    }
}
