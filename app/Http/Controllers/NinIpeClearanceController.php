<?php

namespace App\Http\Controllers;

use App\Models\Ipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NinIpeClearanceController extends Controller
{
    private function sortColumn(?string $sort): string
    {
        return match ($sort) {
            'created_at', 'createdAt' => 'createdAt',
            'updated_at', 'updatedAt' => 'updatedAt',
            'id', 'status' => $sort,
            'nin', 'trkid' => 'trkid',
            default => 'createdAt',
        };
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $balance = (float) $user->balance;

        $price = config('services.nin.ipe_price', 500);

        $query = Ipe::query()
            ->where('userId', $user->id)
            ->with('user');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('trkid', 'like', "%{$search}%")
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

        return Inertia::render('NinIpeClearance/Index', [
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
        $price = (float) config('services.nin.ipe_price', 500);

        if ((float) $user->balance < $price) {
            return back()->withErrors(['message' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        $oldBalance = (float) $user->balance;
        $user->debit($price, false, ['fundingtype' => 'nin_ipe']);

        $reference = Ipe::generateReference();

        Ipe::create([
            'trkid' => $request->nin,
            'status' => 'processing',
            'result' => 'Pending',
            'comment' => "IPE clearance request {$reference}",
            'oldBal' => $oldBalance,
            'newBal' => (float) $user->balance,
            'userId' => $user->id,
        ]);

        // TODO: Call external NIN IPE Clearance API here

        return back()->with('success', 'NIN IPE Clearance submitted successfully. Reference: '.$reference);
    }

    public function checkStatus(Request $request, Ipe $clearance)
    {
        if ($clearance->userId !== Auth::id()) {
            abort(403);
        }

        if ($clearance->status === 'processing') {
            $clearance->update([
                'status' => 'completed',
                'result' => 'NIN IPE Clearance completed successfully',
                'comment' => 'Clearance completed via API',
            ]);
        }

        return back()->with('success', 'Status updated successfully');
    }
}
