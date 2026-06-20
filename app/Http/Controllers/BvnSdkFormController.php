<?php

namespace App\Http\Controllers;

use App\Models\BvnSdkForm;
use App\Models\BvnServicePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * BVN SDK Onboarding — user side.
 *
 * Port of the nimcweb Next.js feature (app/(protectedpages)/bvn_sdk_form):
 * a 3-step onboarding wizard (account / personal / location) that registers an
 * agent's BVN onboarding record and charges the configured `onboarding1` fee
 * from the user's wallet balance.
 */
class BvnSdkFormController extends Controller
{
    /** Zones offered in the form. */
    public const ZONES = ['north-central', 'north-east', 'north-west', 'south-east', 'south-south', 'south-west'];

    private function walletPayload($user): array
    {
        $balance = (float) $user->balance;

        return [
            'balance' => $balance,
            'bonus_balance' => 0.0,
            'total_balance' => $balance,
        ];
    }

    /** The configured onboarding fee, or null when not set. */
    private function onboardingPrice(): ?float
    {
        $value = BvnServicePrice::firstOrCreate(['id' => 'API1'])->onboarding1;

        return ($value === null || $value === '' || ! is_numeric($value)) ? null : (float) $value;
    }

    private function present(BvnSdkForm $f): array
    {
        return [
            'id' => $f->id,
            'agentLocation' => $f->agentLocation,
            'agentBvn' => $f->agentBvn,
            'bankName' => $f->bankName,
            'accountNumber' => $f->accountNumber,
            'accountName' => $f->accountName,
            'firstName' => $f->firstName,
            'lastName' => $f->lastName,
            'email' => $f->email,
            'phoneNumber' => $f->phoneNumber,
            'address' => $f->address,
            'stateOfResidence' => $f->stateOfResidence,
            'lga' => $f->lga,
            'zone' => $f->zone,
            'dateOfBirth' => $f->dateOfBirth,
            'status' => $f->status,
            'comment' => $f->comment,
            'old_balance' => $f->oldBal,
            'new_balance' => $f->newBal,
            'created_at' => $f->createdAt,
            'user' => $f->relationLoaded('user') && $f->user ? [
                'id' => $f->user->id,
                'name' => $f->user->name,
                'username' => $f->user->username,
                'email' => $f->user->email,
            ] : null,
        ];
    }

    /**
     * Show the onboarding form.
     */
    public function index()
    {
        $user = Auth::user();

        return Inertia::render('BvnSdkForm/Index', [
            'wallet' => $this->walletPayload($user),
            'price' => $this->onboardingPrice(),
            'zones' => self::ZONES,
        ]);
    }

    /**
     * List the current user's submissions.
     */
    public function submissions(Request $request)
    {
        $user = Auth::user();

        $query = BvnSdkForm::where('userId', $user->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('firstName', 'like', "%{$search}%")
                    ->orWhere('lastName', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phoneNumber', 'like', "%{$search}%")
                    ->orWhere('stateOfResidence', 'like', "%{$search}%")
                    ->orWhere('zone', 'like', "%{$search}%");
            });
        }

        $forms = $query
            ->orderBy('createdAt', 'desc')
            ->paginate(10)
            ->through(fn (BvnSdkForm $f) => $this->present($f))
            ->withQueryString();

        return Inertia::render('BvnSdkForm/Submissions', [
            'forms' => $forms,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Submit a new onboarding registration.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'agentLocation' => 'required|string|min:3',
            'agentBvn' => 'required|digits:11',
            'bankName' => 'required|string|min:2',
            'accountNumber' => 'required|digits:10',
            'accountName' => 'required|string|min:3',
            'firstName' => 'required|string|min:2',
            'lastName' => 'required|string|min:2',
            'email' => 'required|email|unique:bvnsdkform,email',
            'phoneNumber' => 'required|string|min:11|regex:/^\d+$/|unique:bvnsdkform,phoneNumber',
            'address' => 'required|string|min:5',
            'stateOfResidence' => 'required|string|min:2',
            'dateOfBirth' => 'required|date|before:today',
            'lga' => 'required|string|min:2',
            'zone' => 'required|string|in:'.implode(',', self::ZONES),
        ]);

        $price = $this->onboardingPrice();
        if ($price === null) {
            return back()->withErrors(['message' => 'Onboarding price is not configured. Please contact support.']);
        }

        $user = Auth::user();
        $oldBalance = (float) $user->balance;

        if ($oldBalance < $price) {
            return back()->withErrors(['message' => 'Insufficient balance to complete this transaction. Please top up your account.']);
        }

        if (! $user->debit($price, false, ['fundingtype' => 'bvn_onboarding'])) {
            return back()->withErrors(['message' => 'Insufficient balance to complete this transaction. Please top up your account.']);
        }

        $newBalance = (float) $user->fresh()->balance;

        try {
            BvnSdkForm::create([
                ...$validated,
                'userId' => $user->id,
                'oldBal' => (string) $oldBalance,
                'newBal' => (string) $newBalance,
                'status' => 'Submitted',
            ]);
        } catch (\Throwable $e) {
            $user->credit($price, false, ['fundingtype' => 'refund', 'status' => 'refund']);
            Log::error('BVN SDK onboarding error: '.$e->getMessage());

            return back()->withErrors(['message' => 'Failed to process registration. You have not been charged.']);
        }

        return redirect()->route('bvn-sdk-form.submissions')
            ->with('success', 'Registration successful. ₦'.number_format($price, 2).' has been charged from your wallet.');
    }

    /**
     * Show a single submission (own, or admin).
     */
    public function show(BvnSdkForm $form)
    {
        if ($form->userId !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403);
        }

        $form->load('user');

        return Inertia::render('BvnSdkForm/Show', [
            'form' => $this->present($form),
        ]);
    }
}
