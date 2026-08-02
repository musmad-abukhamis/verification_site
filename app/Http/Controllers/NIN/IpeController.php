<?php

namespace App\Http\Controllers\NIN;

use App\Http\Controllers\Controller;
use App\Models\Ipe;
use App\Services\Nin\AsyncJobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * NIN IPE (Identity Proof of Enrollment) clearance — an asynchronous job.
 *
 * The user submits a 15-character tracking ID, it is filed through the `nin.ipe`
 * routing chain in Admin > Verification, and clearance takes roughly 30 minutes
 * to 3 hours. The Check button on each row polls `nin.ipe.status` for the
 * result; nothing else may close a record.
 *
 * This service never fails over — the request may already have been filed
 * upstream, and a second provider would file it again rather than answer.
 */
class IpeController extends Controller
{
    use NinWalletTrait;

    /** The catalog service this page submits to. */
    private const SERVICE = 'nin.ipe';

    public function __construct(private readonly AsyncJobService $jobs) {}

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
     * Ask the provider how a filed clearance is going.
     *
     * Routed through the provider engine and pinned to whichever provider
     * accepted the job, so it works for any admin-configured vendor rather than
     * one hardcoded host. When the provider cannot be reached the record is left
     * untouched and the user is told the check failed — the previous version
     * marked the clearance `completed` in that case, which reported success to
     * users whenever the upstream API was down.
     */
    public function checkStatus(Request $request, Ipe $clearance)
    {
        if ($clearance->userId !== Auth::id()) {
            abort(403);
        }

        $result = $this->jobs->refresh($clearance, self::SERVICE, ['tracking_id' => $clearance->trkid]);

        if (! $result->reached) {
            return back()->withErrors(['message' => $result->summary()]);
        }

        return back()->with('success', 'IPE status: '.$result->summary());
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
            $outcome = $this->jobs->submit(self::SERVICE, array_filter([
                'tracking_id' => $trackingId,
                'nin' => $nin,
                'description' => $description,
            ]), ['user_id' => $user->id, 'reference' => $reference]);

            if ($outcome->isSuccess()) {
                // Accepted, not cleared. Clearance takes 30 minutes to 3 hours;
                // only a status check may close this record.
                Ipe::create([
                    'trkid' => $trackingId,
                    'status' => 'processing',
                    'result' => 'Pending',
                    'comment' => 'Submitted to '.($outcome->providerName ?? 'provider').' — '.$description,
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'price' => $price,
                    'providerId' => $outcome->providerId,
                    'providerRef' => $outcome->reference,
                    'userId' => $user->id,
                ]);

                return back()->with(
                    'success',
                    "IPE submitted. Ref: {$reference}. Clearance takes 30 minutes to 3 hours — use Check to refresh.",
                );
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
                'price' => $price,
                'providerId' => $outcome->providerId,
                // Already refunded above; recorded so the admin refund button
                // cannot pay a second time on reconciliation.
                'refundedAt' => now(),
                'refundAmount' => $price,
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
