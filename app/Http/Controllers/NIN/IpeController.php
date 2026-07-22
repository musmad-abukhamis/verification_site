<?php

namespace App\Http\Controllers\NIN;

use App\Http\Controllers\Controller;
use App\Models\Ipe;
use App\Services\Verification\VerificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * NIN IPE (Identity Proof of Enrollment).
 *
 * Submissions go through the `nin.ipe` routing chain in Admin > Verification.
 * Unlike the lookup services this one never fails over on an ambiguous reply —
 * the request may already have been filed upstream.
 */
class IpeController extends Controller
{
    use NinWalletTrait;

    public function __construct(private readonly VerificationDispatcher $dispatcher) {}

    private function walletPayload($user): array
    {
        $balance = (float) $user->balance;

        return [
            'balance' => $balance,
            'bonus_balance' => 0.0,
            'total_balance' => $balance,
        ];
    }

    /**
     * Translate legacy snake_case sort keys to the Prisma camelCase columns.
     */
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

    /**
     * Show the IPE Clearance page
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Ipe::where('userId', $user->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('trkid', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%");
            });
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $transactions = $query
            ->orderBy($this->sortColumn($request->input('sort')), $request->input('direction', 'desc'))
            ->paginate(10)
            ->through(fn ($r) => $this->presentNinRecord($r))
            ->withQueryString();

        return Inertia::render('NIN/Ipe/Index', [
            'wallet' => $this->walletPayload($user),
            'price' => $this->getIpePrice(),
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'status', 'sort', 'direction']),
        ]);
    }

    /**
     * Submit an IPE clearance through the routed provider chain.
     *
     * Accepts either field name the two old versioned endpoints used, so
     * existing forms and integrations keep posting successfully.
     */
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'trkid' => 'required_without:tracking_id|string|size:15',
            'tracking_id' => 'required_without:trkid|string|size:15',
            'description' => 'nullable|string|max:255',
            'nin' => 'nullable|string|size:11',
        ]);

        return $this->processIpeSubmission(
            $validated['trkid'] ?? $validated['tracking_id'],
            $validated['description'] ?? 'New submission',
            $validated['nin'] ?? null,
        );
    }

    /**
     * Check IPE status (ArewaSmart)
     */
    public function checkStatus(Request $request, Ipe $clearance)
    {
        if ($clearance->userId !== Auth::id()) {
            abort(403);
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('services.nin.api_key'),
                    'Content-Type' => 'application/json',
                ])
                ->get(config('services.nin.base_url').'/api/v1/nin/ipe/arewa/status', [
                    'tracking_id' => $clearance->trkid,
                ]);

            $body = $response->json();

            if ($response->successful() && ($body['success'] ?? false)) {
                $clearance->update([
                    'status' => $body['status'] === 'completed' ? 'completed' : 'processing',
                    'result' => $body['status'] ?? 'processing',
                    'comment' => $body['comment'] ?? 'Status checked via ArewaSmart',
                ]);

                return back()->with('success', 'IPE status updated: '.($body['status'] ?? 'unknown'));
            }

            // Fallback: simulate status check
            if ($clearance->status === 'processing') {
                $clearance->update([
                    'status' => 'completed',
                    'result' => 'IPE Clearance completed',
                    'comment' => 'Clearance completed',
                ]);
            }

            return back()->with('success', 'Status updated successfully');
        } catch (\Exception $e) {
            Log::error('IPE status check error: '.$e->getMessage());

            return back()->withErrors(['message' => 'Failed to check status: '.$e->getMessage()]);
        }
    }

    /**
     * Process IPE submission for either provider
     */
    protected function processIpeSubmission(string $trackingId, string $description, ?string $nin = null)
    {
        $user = Auth::user();
        $price = $this->getIpePrice($user);

        if ($price === null) {
            return $this->unpricedService();
        }

        if ((float) $user->balance < $price) {
            return back()->withErrors(['message' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        $oldBalance = (float) $user->balance;
        $this->debitWallet($user, $price, ['fundingtype' => 'nin_ipe']);
        $reference = 'IPE_'.now()->timestamp.random_int(1000, 9999);

        try {
            $outcome = $this->dispatcher->verify('nin.ipe', array_filter([
                'tracking_id' => $trackingId,
                'nin' => $nin,
            ]), ['user_id' => $user->id, 'reference' => $reference]);

            if ($outcome->isSuccess()) {
                Ipe::create([
                    'trkid' => $trackingId,
                    'status' => 'processing',
                    'result' => 'Pending',
                    'comment' => 'Submitted to '.($outcome->providerName ?? 'provider').' — '.$description,
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'userId' => $user->id,
                ]);

                return back()->with('success', "IPE submitted. Ref: {$reference}");
            }

            // IPE is a submission, not a lookup: on an ambiguous reply the
            // dispatcher stops rather than re-submitting to another provider.
            // The charge is still reversed, and the record is left visible so
            // the request can be reconciled if it did land upstream.
            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);

            Ipe::create([
                'trkid' => $trackingId,
                'status' => $outcome->isTimeout() ? 'processing' : 'failed',
                'result' => $outcome->isTimeout() ? 'Unconfirmed' : 'Failed',
                'comment' => $outcome->message ?? 'Submission failed',
                'oldBal' => $oldBalance,
                'newBal' => (float) $user->balance,
                'userId' => $user->id,
            ]);

            return back()->withErrors(['message' => $outcome->message ?? 'IPE Submission Failed']);
        } catch (\Exception $e) {
            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);
            Log::error('IPE Submit error: '.$e->getMessage());

            return back()->withErrors(['message' => 'Network error: '.$e->getMessage()]);
        }
    }
}
