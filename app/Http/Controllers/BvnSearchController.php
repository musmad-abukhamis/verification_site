<?php

namespace App\Http\Controllers;

use App\Models\NinDetail;
use App\Services\Bvn\BvnSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * BVN Search — user side.
 *
 * Port of the nimcweb Next.js feature (app/(protectedpages)/bvnsearch +
 * bvnsearch2): verify a BVN against an external provider, render a printable
 * BVN slip, and charge the configured search-slip fee. Attempts (success and
 * failure) are logged to the shared NINDetails table with idtype = "bvn".
 */
class BvnSearchController extends Controller
{
    public function __construct(private BvnSearchService $search)
    {
    }

    private function walletPayload($user): array
    {
        $balance = (float) $user->balance;

        return [
            'balance' => $balance,
            'bonus_balance' => 0.0,
            'total_balance' => $balance,
        ];
    }

    public function index()
    {
        $user = Auth::user();

        $history = NinDetail::where('userId', $user->id)
            ->where('idtype', 'bvn')
            ->orderByDesc('createdAt')
            ->paginate(10)
            ->through(fn (NinDetail $d) => [
                'id' => $d->id,
                'bvn' => $d->idvalue,
                'name' => trim(($d->surname ?? '').' '.($d->othernames ?? '')) ?: null,
                'slip_type' => $d->sliptype,
                'status' => $d->status,
                'price' => $d->price,
                'old_balance' => $d->oldBal,
                'new_balance' => $d->newBal,
                'created_at' => $d->createdAt,
            ]);

        return Inertia::render('BvnSearch/Index', [
            'wallet' => $this->walletPayload($user),
            'slipTypes' => $this->search->activeSlipTypes($user),
            'history' => $history,
        ]);
    }

    /**
     * There is one endpoint now. The v1/v2 split existed only to pick a
     * provider; that choice belongs to the routing chain in
     * Admin > Verification, which also gives failover the versioned endpoints
     * never had.
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'idValue' => 'required|digits:11',
            'slipType' => 'required|string|in:'.implode(',', array_keys(BvnSearchService::SLIP_SERVICES)),
        ]);

        $result = $this->search->search(Auth::user(), $validated['idValue'], $validated['slipType']);

        if (! $result['success']) {
            return back()->withErrors(['message' => $result['message']]);
        }

        return back()->with([
            'success' => 'BVN details fetched successfully.',
            'verification_data' => $result['data'],
        ]);
    }
}
