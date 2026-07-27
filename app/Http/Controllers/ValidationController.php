<?php

namespace App\Http\Controllers;

use App\Models\Validation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ValidationController extends Controller
{
    private function sortColumn(?string $sort): string
    {
        return match ($sort) {
            'created_at', 'createdAt' => 'createdAt',
            'updated_at', 'updatedAt' => 'updatedAt',
            'id', 'nin', 'status' => $sort,
            default => 'createdAt',
        };
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $balance = (float) $user->balance;

        $price = config('services.verification.nin_price', 100);

        $query = Validation::query()
            ->where('userId', $user->id)
            ->with('user');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nin', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $transactions = $query
            ->orderBy($this->sortColumn($request->input('sort')), $request->input('direction', 'desc'))
            ->paginate(10)
            ->through(fn ($v) => [
                'id' => $v->id,
                'reference' => $v->id,
                'nin' => $v->nin,
                'status' => $v->status,
                'result' => $v->result,
                'comment' => $v->comment,
                'old_balance' => (float) $v->oldBal,
                'new_balance' => (float) $v->newBal,
                'created_at' => $v->createdAt,
            ])
            ->withQueryString();

        return Inertia::render('Validation/Index', [
            'price' => $price,
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'status', 'sort', 'direction']),
            'wallet' => [
                'balance' => $balance,
                'bonus_balance' => 0.0,
                'total_balance' => $balance,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nin' => 'required|string|size:11',
        ]);

        $user = Auth::user();
        $price = (float) config('services.verification.nin_price', 100);

        if ((float) $user->balance < $price) {
            return back()->withErrors(['message' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        $oldBalance = (float) $user->balance;
        $user->debit($price, false, ['fundingtype' => 'nin_validation']);

        $reference = Validation::generateReference();

        Validation::create([
            'nin' => $request->nin,
            'status' => 'processing',
            'comment' => "Validation request {$reference}",
            'oldBal' => $oldBalance,
            'newBal' => (float) $user->balance,
            'userId' => $user->id,
        ]);

        // TODO: Call external NIN validation API here

        return back()->with('success', 'NIN validation submitted successfully. Reference: '.$reference);
    }

    public function checkStatus(Request $request, Validation $validation)
    {
        if ($validation->userId !== Auth::id()) {
            abort(403);
        }

        if ($validation->status === 'processing') {
            $validation->update([
                'status' => 'completed',
                'result' => 'NIN validated successfully',
                'comment' => 'Validation completed via API',
            ]);
        }

        return back()->with('success', 'Status updated successfully');
    }
}
