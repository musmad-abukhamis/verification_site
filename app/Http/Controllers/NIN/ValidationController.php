<?php

namespace App\Http\Controllers\NIN;

use App\Http\Controllers\Controller;
use App\Models\Validation;
use App\Services\Nin\AsyncJobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * NIN Validation — an asynchronous job, not a lookup.
 *
 * The user submits a NIN, it is filed with the provider through the
 * `nin.validation` routing chain, and the work takes roughly 3 days to a week.
 * The submission itself only ever produces a `processing` record; the Check
 * button on each row polls `nin.validation.status` for the real answer.
 *
 * This is a different service from NIN Verification, which is an instant lookup
 * and lives in VerifyController. An earlier version of this file called the
 * verification chain and marked every row `completed` on the spot, which meant
 * users were charged the validation fee for a verification result and were never
 * told what actually happened to their validation.
 */
class ValidationController extends Controller
{
    use NinWalletTrait;

    /** The catalog service this page submits to. */
    private const SERVICE = 'nin.validation';

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

    private function sortColumn(?string $sort): string
    {
        return match ($sort) {
            'created_at', 'createdAt' => 'createdAt',
            'updated_at', 'updatedAt' => 'updatedAt',
            'id', 'nin', 'status' => $sort,
            default => 'createdAt',
        };
    }

    /**
     * Show the NIN Validation page
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Validation::where('userId', $user->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nin', 'like', "%{$search}%")
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

        return Inertia::render('NIN/Validation/Index', [
            'wallet' => $this->walletPayload($user),
            'price' => $this->getValidationPrice(),
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'status', 'sort', 'direction']),
        ]);
    }

    /**
     * File a NIN validation with the provider.
     */
    public function store(Request $request)
    {
        return $this->processValidation($request);
    }

    /**
     * Ask the provider how a filed validation is going.
     *
     * Only the provider's answer moves the record. When it cannot be reached the
     * row stays `processing` and the user is told the check failed, rather than
     * being shown an invented completion.
     */
    public function checkStatus(Request $request, Validation $validation)
    {
        if ($validation->userId !== Auth::id()) {
            abort(403);
        }

        $result = $this->jobs->refresh($validation, self::SERVICE, ['nin' => $validation->nin]);

        if (! $result->reached) {
            return back()->withErrors(['message' => $result->summary()]);
        }

        return back()->with('success', 'Validation status: '.$result->summary());
    }

    protected function processValidation(Request $request)
    {
        $validated = $request->validate([
            'nin' => 'required|string|size:11',
        ]);

        $user = Auth::user();
        $price = $this->getValidationPrice($user);

        if ($price === null) {
            return $this->unpricedService();
        }

        if ((float) $user->balance < $price) {
            return back()->withErrors(['message' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        $oldBalance = (float) $user->balance;
        $this->debitWallet($user, $price, ['fundingtype' => 'nin_validation']);
        $reference = 'NIN_'.now()->timestamp.random_int(1000, 9999);

        try {
            $outcome = $this->jobs->submit(self::SERVICE, [
                'nin' => $validated['nin'],
                'description' => $reference,
            ], ['user_id' => $user->id, 'reference' => $reference]);

            if ($outcome->isSuccess()) {
                // Accepted, not finished. A validation takes days, and the
                // acceptance reply says nothing about the outcome — ArewaSmart
                // even reports `"status": "successful"` here, meaning only that
                // the request was created.
                Validation::create([
                    'nin' => $validated['nin'],
                    'status' => 'processing',
                    'result' => 'Pending',
                    'comment' => 'Submitted to '.($outcome->providerName ?? 'provider').' — awaiting result',
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'price' => $price,
                    'providerId' => $outcome->providerId,
                    'providerRef' => $outcome->reference,
                    'userId' => $user->id,
                ]);

                return back()->with(
                    'success',
                    "Validation submitted. Ref: {$reference}. Results take 3 days to a week — use Check to refresh.",
                );
            }

            // Like IPE, this files work upstream: on an ambiguous reply the
            // dispatcher stops rather than submitting to a second provider. The
            // charge is reversed either way, and an unconfirmed submission is
            // kept visible as `processing` so it can be reconciled if it did
            // land.
            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);

            $message = $outcome->message ?? 'Validation submission failed';

            Validation::create([
                'nin' => $validated['nin'],
                'status' => $outcome->isTimeout() ? 'processing' : 'failed',
                'result' => $outcome->isTimeout() ? 'Unconfirmed' : 'Failed',
                'comment' => $message,
                'oldBal' => $oldBalance,
                'newBal' => (float) $user->balance,
                'price' => $price,
                'providerId' => $outcome->providerId,
                'refundedAt' => now(),
                'refundAmount' => $price,
                'userId' => $user->id,
            ]);

            return back()->withErrors(['message' => $message]);
        } catch (\Exception $e) {
            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);
            Log::error('NIN Validation error: '.$e->getMessage());

            return back()->withErrors(['message' => 'Network error: '.$e->getMessage()]);
        }
    }
}
