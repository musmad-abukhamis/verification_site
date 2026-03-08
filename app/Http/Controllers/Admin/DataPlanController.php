<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class DataPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Admin/DataPlans/Index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/DataPlans/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'network' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'agentPrice' => 'required|integer|min:0',
            'apiPrice' => 'required|integer|min:0',
            'type' => 'required|string|max:255',
            'validity' => 'required|string|max:255',
            'status' => 'required|string|in:on,off',
            'planStatus' => 'required|string|in:on,off',
            'apiKey' => 'nullable|integer|unique:plans,apiKey',
            'vendorPlan1' => 'nullable|string|max:255',
            'vendorPlan2' => 'nullable|string|max:255',
            'vendorPlan3' => 'nullable|string|max:255',
            'vendorPlan4' => 'nullable|string|max:255',
            'vendorPlan5' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        Plan::create($validator->validated());

        return redirect()->route('admin.dataplan.index')->with('success', 'Data plan created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $plan = Plan::findOrFail($id);
        return Inertia::render('Admin/DataPlans/Show', [
            'plan' => $plan
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $plan = Plan::findOrFail($id);
        return Inertia::render('Admin/DataPlans/Edit', [
            'plan' => $plan
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $plan = Plan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'network' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'agentPrice' => 'required|integer|min:0',
            'apiPrice' => 'required|integer|min:0',
            'type' => 'required|string|max:255',
            'validity' => 'required|string|max:255',
            'status' => 'required|string|in:on,off',
            'planStatus' => 'required|string|in:on,off',
            'apiKey' => 'nullable|integer|unique:plans,apiKey,' . $plan->id,
            'vendorPlan1' => 'nullable|string|max:255',
            'vendorPlan2' => 'nullable|string|max:255',
            'vendorPlan3' => 'nullable|string|max:255',
            'vendorPlan4' => 'nullable|string|max:255',
            'vendorPlan5' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $plan->update($validator->validated());

        return redirect()->route('admin.dataplan.index')->with('success', 'Data plan updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $plan = Plan::findOrFail($id);
        $plan->delete();

        return redirect()->route('admin.dataplan.index')->with('success', 'Data plan deleted successfully.');
    }
}