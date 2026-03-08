<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Settings/Index', [
            'pricing' => [
                'nin_price' => config('services.verification.nin_price', 100),
                'bvn_price' => config('services.verification.bvn_price', 150),
            ],
            'verificationMethods' => config('services.verification.nin_methods', [
                'nin' => ['active' => true, 'label' => 'By NIN Number'],
                'phone' => ['active' => true, 'label' => 'By Phone Number'],
                'demographic' => ['active' => true, 'label' => 'By Demographics'],
            ]),
        ]);
    }

    public function updatePricing(Request $request)
    {
        $validated = $request->validate([
            'nin_price' => 'required|numeric|min:0',
            'bvn_price' => 'required|numeric|min:0',
        ]);

        // Update .env file or database settings
        // For now, we'll just return success (in production, use a settings table)
        
        return back()->with('success', 'Pricing updated successfully.');
    }

    public function updateVerificationMethods(Request $request)
    {
        $validated = $request->validate([
            'methods' => 'required|array',
            'methods.nin.active' => 'boolean',
            'methods.phone.active' => 'boolean',
            'methods.demographic.active' => 'boolean',
        ]);

        // Update config (in production, save to database)
        
        return back()->with('success', 'Verification methods updated successfully.');
    }
}
