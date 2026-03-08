<?php

namespace App\Http\Controllers;

use App\Models\NinIpeClearance;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NinIpeClearanceController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get wallet
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );
        
        // Get NIN IPE Clearance price from config
        $price = config('services.nin.ipe_price', 500);
        
        // Build query
        $query = NinIpeClearance::query()
            ->where('user_id', $user->id)
            ->with('user');
        
        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nin', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }
        
        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        
        // Sorting
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $allowedSorts = ['id', 'nin', 'status', 'created_at', 'updated_at'];
        
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }
        
        $transactions = $query->paginate(10)->withQueryString();
        
        return Inertia::render('NinIpeClearance/Index', [
            'price' => $price,
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'status', 'sort', 'direction']),
            'wallet' => [
                'balance' => $wallet->balance,
                'bonus_balance' => $wallet->bonus_balance,
                'total_balance' => $wallet->total_balance,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nin' => 'required|string|size:11',
        ]);
        
        $user = Auth::user();
        $price = config('services.nin.ipe_price', 500);
        
        // Check wallet balance
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );
        
        if ($wallet->total_balance < $price) {
            return back()->withErrors(['message' => 'Insufficient wallet balance. Please fund your wallet.']);
        }
        
        $oldBalance = $wallet->total_balance;
        
        // Deduct from wallet
        if ($wallet->bonus_balance >= $price) {
            $wallet->bonus_balance -= $price;
        } else {
            $remaining = $price - $wallet->bonus_balance;
            $wallet->bonus_balance = 0;
            $wallet->balance -= $remaining;
        }
        $wallet->save();
        
        // Create clearance record
        $clearance = NinIpeClearance::create([
            'user_id' => $user->id,
            'nin' => $request->nin,
            'status' => 'processing',
            'old_balance' => $oldBalance,
            'new_balance' => $wallet->total_balance,
            'reference' => NinIpeClearance::generateReference(),
        ]);
        
        // TODO: Call external NIN IPE Clearance API here
        // For now, simulate processing
        
        return back()->with('success', 'NIN IPE Clearance submitted successfully. Reference: ' . $clearance->reference);
    }

    public function checkStatus(Request $request, NinIpeClearance $clearance)
    {
        // Ensure user owns this clearance
        if ($clearance->user_id !== Auth::id()) {
            abort(403);
        }
        
        // TODO: Call external API to check status
        // For now, simulate a successful clearance
        
        if ($clearance->status === 'processing') {
            $clearance->update([
                'status' => 'completed',
                'result' => 'NIN IPE Clearance completed successfully',
                'comment' => 'Clearance completed via API',
                'cleared_at' => now(),
            ]);
        }
        
        return back()->with('success', 'Status updated successfully');
    }
}
