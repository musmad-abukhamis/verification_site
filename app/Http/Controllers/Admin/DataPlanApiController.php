<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class DataPlanApiController extends Controller
{
    /**
     * Return all data plans as JSON for the listing page
     */
    public function getAll()
    {
        $plans = Plan::all();
        return response()->json($plans);
    }

    /**
     * Return single data plan as JSON
     */
    public function getById($id)
    {
        $plan = Plan::findOrFail($id);
        return response()->json($plan);
    }
}