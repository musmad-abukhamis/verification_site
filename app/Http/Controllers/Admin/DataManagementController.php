<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\VendorApi;
use App\Models\VendorSelection;
use Inertia\Inertia;

class DataManagementController extends Controller
{
    /**
     * Display data management overview
     */
    public function index()
    {
        $stats = [
            'total_plans' => Plan::count(),
            'active_vendors' => VendorSelection::count(),
            'total_transactions' => Transaction::where('type', 'data')->count(),
            'successful_transactions' => Transaction::where('type', 'data')->where('status', 'success')->count(),
            'failed_transactions' => Transaction::where('type', 'data')->where('status', 'fail')->count(),
        ];

        return Inertia::render('Admin/DataManagement/Index', [
            'stats' => $stats,
        ]);
    }

    /**
     * Display data plans management
     */
    public function plans()
    {
        $plans = Plan::orderBy('network')->orderBy('price')->get();
        $vendorApi = VendorApi::first();

        // Transform plans to match Vue component expectations
        $transformedPlans = $plans->map(function (Plan $plan) {
            return [
                'id' => $plan->id,
                'network' => $plan->network,
                'type' => $plan->type,
                'name' => $plan->name,
                'size' => $plan->name, // Using name as size since we don't have data_volume
                'validity' => $plan->validity,
                'price' => $plan->price,
                'is_active' => $plan->status === 'on',
                'vendor_plans' => collect(range(1, 5))->map(fn ($n) => [
                    'id' => $n,
                    'vendor_number' => $n,
                    'is_active' => $plan->{'vendorPlan'.$n} !== '000',
                ])->all(),
            ];
        });

        return Inertia::render('Admin/DataManagement/Plans', [
            'plans' => $transformedPlans,
            'vendorApi' => $vendorApi,
        ]);
    }

    /**
     * Display networks management (active vendor per network + service type).
     */
    public function networks()
    {
        $vendors = [
            ['id' => 1, 'name' => 'VTpass'],
            ['id' => 2, 'name' => 'ClubKonnect'],
            ['id' => 3, 'name' => 'Monnify'],
            ['id' => 4, 'name' => 'Flutterwave'],
            ['id' => 5, 'name' => 'Paystack'],
        ];

        $types = ['SME', 'SME2', 'CORPORATE_GIFTING', 'CORPORATE_GIFTING2', 'DATASHARE'];

        // Flatten the per-network vendorselection rows into the shape the Vue
        // table expects: {network, type, vendor_number, prefixes, is_active}.
        $transformedNetworks = collect(['MTN', 'GLO', 'AIRTEL', '9MOBILE'])
            ->flatMap(function (string $network) use ($types) {
                $selection = VendorSelection::firstOrCreate(['id' => $network]);

                return collect($types)->map(fn (string $type) => [
                    'network' => strtolower($network),
                    'type' => $type,
                    'vendor_number' => (int) ($selection->{$type} ?? 1),
                    'prefixes' => null,
                    'is_active' => true,
                ]);
            })
            ->values();

        return Inertia::render('Admin/DataManagement/Networks', [
            'networks' => $transformedNetworks,
            'vendors' => $vendors,
        ]);
    }

    /**
     * Display data transactions
     */
    public function transactions()
    {
        $transactions = Transaction::where('type', 'data')
            ->with('user')
            ->orderByDesc('createdAt')
            ->paginate(20);

        // Transform transactions to match Vue component expectations
        $transformedTransactions = $transactions->through(fn (Transaction $transaction) => [
            'id' => $transaction->id,
            'user' => $transaction->user ? [
                'name' => $transaction->user->name,
                'email' => $transaction->user->email,
            ] : null,
            'reference' => $transaction->reference,
            'amount' => (float) $transaction->price,
            'network' => $transaction->network,
            'phone' => $transaction->phone,
            'status' => $transaction->status,
            'created_at' => $transaction->createdAt,
        ]);

        return Inertia::render('Admin/DataManagement/Transactions', [
            'transactions' => $transformedTransactions,
        ]);
    }
}
