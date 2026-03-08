<?php

namespace App\Http\Controllers;

use App\Models\NinValidation;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ValidationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get wallet
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );
        
        // Get NIN validation price from config
        $price = config('services.verification.nin_price', 100);
        
        // Build query
        $query = NinValidation::query()
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
        
        return Inertia::render('Validation/Index', [
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
        $price = config('services.verification.nin_price', 100);
        
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
        
        // Create validation record
        $validation = NinValidation::create([
            'user_id' => $user->id,
            'nin' => $request->nin,
            'status' => 'processing',
            'old_balance' => $oldBalance,
            'new_balance' => $wallet->total_balance,
            'reference' => NinValidation::generateReference(),
        ]);
        
        // TODO: Call external NIN validation API here
        // For now, simulate processing
        
        return back()->with('success', 'NIN validation submitted successfully. Reference: ' . $validation->reference);
    }

    public function checkStatus(Request $request, NinValidation $validation)
    {
        // Ensure user owns this validation
        if ($validation->user_id !== Auth::id()) {
            abort(403);
        }
        
        // TODO: Call external API to check status
        // For now, simulate a successful validation
        
        if ($validation->status === 'processing') {
            $validation->update([
                'status' => 'completed',
                'result' => 'NIN validated successfully',
                'comment' => 'Validation completed via API',
                'validated_at' => now(),
            ]);
        }
        
        return back()->with('success', 'Status updated successfully');
    }
}
