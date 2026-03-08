<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorSelection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VendorSelectionController extends Controller
{
    public function index()
    {
        // Fetch all vendor selections to pass to the frontend
        $vendorSelections = [];
        $networkIds = ['MTN', 'AIRTEL', 'GLO', '9MOBILE'];
        
        foreach ($networkIds as $networkId) {
            $selection = VendorSelection::firstOrCreate(
                ['id' => $networkId],
                [
                    'SME' => '1',
                    'SME2' => '1',
                    'CORPORATE_GIFTING' => '1',
                    'CORPORATE_GIFTING2' => '1',
                    'DATASHARE' => '1'
                ]
            );
            $vendorSelections[$networkId] = $selection;
        }

        return inertia('Admin/VendorSelection/DataVendorSelector', [
            'vendorSelections' => $vendorSelections
        ]);
    }

    public function getSelection($networkId)
    {
        $validator = Validator::make(['id' => $networkId], [
            'id' => 'required|string|in:MTN,AIRTEL,GLO,9MOBILE'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid network ID'], 422);
        }

        $selection = VendorSelection::firstOrCreate(
            ['id' => $networkId],
            [
                'SME' => '1',
                'SME2' => '1',
                'CORPORATE_GIFTING' => '1',
                'CORPORATE_GIFTING2' => '1',
                'DATASHARE' => '1'
            ]
        );

        return response()->json($selection);
    }

    public function saveSelection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string|in:MTN,AIRTEL,GLO,9MOBILE',
            'SME' => 'required|string|in:1,2,3,4,5',
            'SME2' => 'required|string|in:1,2,3,4,5',
            'CORPORATE_GIFTING' => 'required|string|in:1,2,3,4,5',
            'CORPORATE_GIFTING2' => 'required|string|in:1,2,3,4,5',
            'DATASHARE' => 'required|string|in:1,2,3,4,5',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $selection = VendorSelection::updateOrCreate(
            ['id' => $request->id],
            [
                'SME' => $request->SME,
                'SME2' => $request->SME2,
                'CORPORATE_GIFTING' => $request->CORPORATE_GIFTING,
                'CORPORATE_GIFTING2' => $request->CORPORATE_GIFTING2,
                'DATASHARE' => $request->DATASHARE
            ]
        );

        return response()->json([
            'message' => 'Vendor selection updated successfully',
            'data' => $selection
        ]);
    }
}