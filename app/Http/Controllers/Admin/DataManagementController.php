<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActiveVendor;
use App\Models\DataPlan;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\VendorApi;
use App\Models\VendorNetwork;
use App\Models\VendorPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DataManagementController extends Controller
{
    /**
     * Display data management overview
     */
    public function index()
    {
        $stats = [
            'total_plans' => DataPlan::count(),
            'active_vendors' => ActiveVendor::count(),
            'total_transactions' => Transaction::where('type', 'data')->count(),
            'successful_transactions' => Transaction::where('type', 'data')->where('status', 'success')->count(),
            'failed_transactions' => Transaction::where('type', 'data')->where('status', 'fail')->count(),
        ];

        return Inertia::render('Admin/DataManagement/Index', [
            'stats' => $stats
        ]);
    }

    /**
     * Display data plans management
     */
    public function plans()
    {
        // Get plans from the new plans table
        $plans = Plan::orderBy('network')->orderBy('price')->get();
        $vendorApi = VendorApi::first();

        // Transform plans to match Vue component expectations
        $transformedPlans = $plans->map(function ($plan) {
            return [
                'id' => $plan->id,
                'network' => $plan->network,
                'type' => $plan->type,
                'name' => $plan->name,
                'size' => $plan->name, // Using name as size since we don't have data_volume
                'validity' => $plan->validity,
                'price' => $plan->price,
                'is_active' => $plan->status === 'on',
                'vendor_plans' => [
                    [
                        'id' => 1,
                        'vendor_number' => 1,
                        'is_active' => $plan->vendorPlan1 !== '000',
                    ],
                    [
                        'id' => 2,
                        'vendor_number' => 2,
                        'is_active' => $plan->vendorPlan2 !== '000',
                    ],
                    [
                        'id' => 3,
                        'vendor_number' => 3,
                        'is_active' => $plan->vendorPlan3 !== '000',
                    ],
                    [
                        'id' => 4,
                        'vendor_number' => 4,
                        'is_active' => $plan->vendorPlan4 !== '000',
                    ],
                    [
                        'id' => 5,
                        'vendor_number' => 5,
                        'is_active' => $plan->vendorPlan5 !== '000',
                    ],
                ]
            ];
        });

        return Inertia::render('Admin/DataManagement/Plans', [
            'plans' => $transformedPlans,
            'vendorApi' => $vendorApi
        ]);
    }

    /**
     * Display networks management
     */
    public function networks()
    {
        $networks = VendorNetwork::all();
        $vendors = [
            ['id' => 1, 'name' => 'VTpass'],
            ['id' => 2, 'name' => 'ClubKonnect'],
            ['id' => 3, 'name' => 'Monnify'],
            ['id' => 4, 'name' => 'Flutterwave'],
            ['id' => 5, 'name' => 'Paystack'],
        ];

        // Transform networks to match Vue component expectations
        $transformedNetworks = $networks->map(function ($network) {
            return [
                'network' => $network->network,
                'type' => $network->type,
                'vendor_number' => $network->vendor_number,
                'prefixes' => $network->prefixes,
                'is_active' => $network->is_active,
            ];
        });

        return Inertia::render('Admin/DataManagement/Networks', [
            'networks' => $transformedNetworks,
            'vendors' => $vendors
        ]);
    }

    /**
     * Display data transactions
     */
    public function transactions()
    {
        $transactions = Transaction::where('type', 'data')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Transform transactions to match Vue component expectations
        $transformedTransactions = $transactions->through(function ($transaction) {
            return [
                'id' => $transaction->id,
                'user' => $transaction->user ? [
                    'name' => $transaction->user->name,
                    'email' => $transaction->user->email,
                ] : null,
                'reference' => $transaction->reference,
                'amount' => $transaction->amount,
                'network' => $transaction->network,
                'phone' => $transaction->phone,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at,
            ];
        });

        return Inertia::render('Admin/DataManagement/Transactions', [
            'transactions' => $transformedTransactions
        ]);
    }
}