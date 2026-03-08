<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorApiRequest;
use App\Models\ActiveVendor;
use App\Models\VendorApi;
use App\Models\VendorNetwork;
use App\Models\VendorPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class VendorController extends Controller
{
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

        $activeVendors = ActiveVendor::all();

        return Inertia::render('Admin/Vendors/Index', [
            'vendors' => $vendors,
            'activeVendors' => $activeVendors
        ]);
    }

    /**
     * Show vendor API configuration
     */
    public function apiConfig()
    {
        $vendorApi = VendorApi::first();
        
        return Inertia::render('Admin/Vendors/ApiConfig', [
            'vendorApi' => $vendorApi ?? new VendorApi()
        ]);
    }

    /**
     * Update vendor API configuration
     */
    public function updateApiConfig(VendorApiRequest $request)
    {
        $vendorApi = VendorApi::first();
        if (!$vendorApi) {
            $vendorApi = new VendorApi();
        }

        $vendorApi->fill($request->validated());
        $vendorApi->save();

        return response()->json([
            'message' => 'Vendor API configuration updated successfully!',
            'data' => $vendorApi
        ], 200);
    }

    /**
     * Show active vendors configuration
     */
    public function activeVendors()
    {
        $activeVendors = ActiveVendor::all();
        $networks = ['mtn', 'glo', 'airtel', '9mobile'];
        $types = ['sme', 'direct', 'corporate'];
        $vendors = [
            ['id' => 1, 'name' => 'VTpass'],
            ['id' => 2, 'name' => 'ClubKonnect'],
            ['id' => 3, 'name' => 'Monnify'],
            ['id' => 4, 'name' => 'Flutterwave'],
            ['id' => 5, 'name' => 'Paystack'],
        ];

        // Create default configurations if none exist
        if ($activeVendors->isEmpty()) {
            foreach ($networks as $network) {
                foreach ($types as $type) {
                    ActiveVendor::create([
                        'network' => $network,
                        'type' => $type,
                        'vendor_number' => 1
                    ]);
                }
            }
            $activeVendors = ActiveVendor::all();
        }

        return Inertia::render('Admin/Vendors/ActiveVendors', [
            'activeVendors' => $activeVendors,
            'networks' => $networks,
            'types' => $types,
            'vendors' => $vendors
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
            ActiveVendor::updateOrCreate(
                [
                    'network' => $config['network'],
                    'type' => $config['type']
                ],
                [
                    'vendor_number' => $config['vendor_number']
                ]
            );
        }

        return back()->with('success', 'Active vendors configuration updated successfully.');
    }
}