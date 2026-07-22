<?php

namespace App\Http\Controllers\NIN;

use App\Http\Controllers\Controller;
use App\Models\Validation;
use App\Services\Nin\Providers\RoutedProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * NIN Validation: submit a NIN, verify it through the routed provider chain,
 * and log the result. The provider comes from Admin > Verification.
 */
class ValidationController extends Controller
{
    use NinWalletTrait;

    public function __construct(private readonly RoutedProvider $routed) {}

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
     * Validate a NIN through the routed provider chain.
     */
    public function store(Request $request)
    {
        return $this->processValidation($request);
    }

    /**
     * Check status of a pending NIN validation
     */
    public function checkStatus(Request $request, Validation $validation)
    {
        if ($validation->userId !== Auth::id()) {
            abort(403);
        }

        if ($validation->status === 'processing') {
            $validation->update([
                'status' => 'completed',
                'result' => 'NIN validated successfully',
                'comment' => 'Validation completed',
            ]);
        }

        return back()->with('success', 'Status updated successfully');
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
            $result = $this->routed->verifyByNin($validated['nin']);

            if ($result->success) {
                $body = $result->data ?? [];

                Validation::create([
                    'nin' => $validated['nin'],
                    'status' => 'completed',
                    'result' => json_encode($body),
                    'comment' => 'NIN validation via '.($body['provider'] ?? 'routing'),
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'userId' => $user->id,
                ]);

                return back()->with('success', "NIN validated successfully. Reference: {$reference}");
            }

            // Refund on failure
            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);

            $errorMessage = $result->message ?? 'Validation failed';

            Validation::create([
                'nin' => $validated['nin'],
                'status' => 'failed',
                'result' => json_encode($result->raw ?? ['message' => $errorMessage]),
                'comment' => $errorMessage,
                'oldBal' => $oldBalance,
                'newBal' => (float) $user->balance,
                'userId' => $user->id,
            ]);

            return back()->withErrors(['message' => $errorMessage]);
        } catch (\Exception $e) {
            $this->creditWallet($user, $price, ['fundingtype' => 'refund', 'status' => 'refund']);
            Log::error('NIN Validation error: '.$e->getMessage());

            return back()->withErrors(['message' => 'Network error: '.$e->getMessage()]);
        }
    }
}
