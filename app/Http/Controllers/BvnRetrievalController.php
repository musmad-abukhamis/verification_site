<?php

namespace App\Http\Controllers;

use App\Models\BvnRetrieval;
use App\Models\BvnServicePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * BVN Retrieval — user side.
 *
 * Port of the nimcweb Next.js feature (app/(protectedpages)/bvn-retrieval):
 * a user submits a retrieval request with their 8-digit Ticket ID (BMS ID),
 * is charged the `retrieve_with_Id` fee, and an admin later fills in the
 * retrieved BVN and marks the request complete.
 */
class BvnRetrievalController extends Controller
{
    private function walletPayload($user): array
    {
        $balance = (float) $user->balance;

        return [
            'balance' => $balance,
            'bonus_balance' => 0.0,
            'total_balance' => $balance,
        ];
    }

    /** The configured ID-retrieval fee, or null when not set. */
    private function retrievalPrice(): ?float
    {
        $value = BvnServicePrice::firstOrCreate(['id' => 'API1'])->retrieve_with_Id;

        return ($value === null || $value === '' || ! is_numeric($value)) ? null : (float) $value;
    }

    private function present(BvnRetrieval $r): array
    {
        return [
            'id' => $r->id,
            'firstname' => $r->firstname,
            'middlename' => $r->middlename,
            'surname' => $r->surname,
            'phone' => $r->phone,
            'retrievalType' => $r->retrievalType,
            'ticketId1' => $r->ticketId1,
            'ticketId2' => $r->ticketId2,
            'batchId' => $r->batchId,
            'nin' => $r->nin,
            'bvn' => $r->bvn,
            'status' => $r->status,
            'comment' => $r->comment,
            'old_balance' => $r->oldBal,
            'new_balance' => $r->newBal,
            'created_at' => $r->createdAt,
            'updated_at' => $r->updatedAt,
            'user' => $r->relationLoaded('user') && $r->user ? [
                'id' => $r->user->id,
                'name' => $r->user->name,
                'username' => $r->user->username,
                'email' => $r->user->email,
                'phone' => $r->user->phone,
            ] : null,
        ];
    }

    /**
     * Show the form together with the user's own requests.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = BvnRetrieval::where('userId', $user->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ticketId1', 'like', "%{$search}%")
                    ->orWhere('ticketId2', 'like', "%{$search}%")
                    ->orWhere('batchId', 'like', "%{$search}%")
                    ->orWhere('nin', 'like', "%{$search}%")
                    ->orWhere('bvn', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        $requests = $query
            ->orderBy('createdAt', 'desc')
            ->paginate(10)
            ->through(fn (BvnRetrieval $r) => $this->present($r))
            ->withQueryString();

        return Inertia::render('BvnRetrieval/Index', [
            'wallet' => $this->walletPayload($user),
            'price' => $this->retrievalPrice(),
            'requests' => $requests,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Submit a new retrieval request (Ticket/BMS ID).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bmsId' => 'required|digits:8',
        ]);

        $price = $this->retrievalPrice();
        if ($price === null) {
            return back()->withErrors(['message' => 'Service price for ID retrieval is not configured. Please contact support.']);
        }

        $user = Auth::user();
        $oldBalance = (float) $user->balance;

        if ($oldBalance < $price) {
            return back()->withErrors(['message' => 'Insufficient balance. Please fund your account.']);
        }

        if (! $user->debit($price, false, ['fundingtype' => 'bvn_retrieval'])) {
            return back()->withErrors(['message' => 'Insufficient balance. Please fund your account.']);
        }

        $newBalance = (float) $user->fresh()->balance;

        try {
            BvnRetrieval::create([
                'firstname' => '-',
                'middlename' => null,
                'surname' => '-',
                'phone' => '-',
                'retrievalType' => 'id',
                'bvn' => '',           // filled by admin later
                'ticketId1' => '',
                'ticketId2' => $validated['bmsId'],
                'batchId' => '',
                'nin' => '',
                'oldBal' => (string) $oldBalance,
                'newBal' => (string) $newBalance,
                'status' => 'pending',
                'comment' => null,
                'userId' => $user->id,
            ]);
        } catch (\Throwable $e) {
            $user->credit($price, false, ['fundingtype' => 'refund', 'status' => 'refund']);
            Log::error('BVN retrieval error: '.$e->getMessage());

            return back()->withErrors(['message' => 'Failed to submit request. You have not been charged.']);
        }

        return redirect()->route('bvn-retrieval.index')
            ->with('success', 'BVN retrieval request submitted successfully.');
    }
}
