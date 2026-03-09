<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServicePrice;
use App\Models\SlipType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServicePriceController extends Controller
{
    /**
     * Display the service prices management page
     */
    public function index()
    {
        $servicePrices = ServicePrice::orderBy('service_type')->get();
        $slipTypes = SlipType::orderBy('sort_order')->get();

        return Inertia::render('Admin/ServicePrices/Index', [
            'servicePrices' => $servicePrices,
            'slipTypes' => $slipTypes,
        ]);
    }

    /**
     * Update a service price
     */
    public function updateServicePrice(Request $request, ServicePrice $servicePrice)
    {
        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $servicePrice->update($validated);

        return back()->with('success', 'Service price updated successfully.');
    }

    /**
     * Update a slip type
     */
    public function updateSlipType(Request $request, SlipType $slipType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $slipType->update($validated);

        return back()->with('success', 'Slip type updated successfully.');
    }

    /**
     * Create a new slip type
     */
    public function storeSlipType(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:slip_types,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'component_name' => 'required|string|max:255',
            'sort_order' => 'integer|min:0',
        ]);

        SlipType::create($validated);

        return back()->with('success', 'Slip type created successfully.');
    }

    /**
     * Delete a slip type
     */
    public function destroySlipType(SlipType $slipType)
    {
        $slipType->delete();

        return back()->with('success', 'Slip type deleted successfully.');
    }
}
