<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Http\Controllers\BvnSdkFormController as WebController;
use App\Http\Controllers\Controller;
use App\Models\BvnSdkForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * BVN SDK agent onboarding for API resellers.
 *
 * Registers an agent for BVN enrolment — the API form of the 3-step web wizard,
 * charged from `bvn.onboarding1`. Fulfilled by our staff, so the response is an
 * accepted registration whose status an admin moves on later.
 *
 * Email and phone number are unique across all onboardings, not just the
 * caller's: an agent is one person, and the same person registered twice is a
 * duplicate upstream whoever submitted it.
 */
class BvnOnboardingController extends Controller
{
    use Concerns\FulfilledService;

    private const SERVICE = 'bvn.onboarding1';

    /**
     * Register an agent for onboarding.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_location' => ['required', 'string', 'min:3', 'max:255'],
            'agent_bvn' => ['required', 'string', 'regex:/^\d{11}$/'],
            'bank_name' => ['required', 'string', 'min:2', 'max:255'],
            'account_number' => ['required', 'string', 'regex:/^\d{10}$/'],
            'account_name' => ['required', 'string', 'min:3', 'max:255'],
            'first_name' => ['required', 'string', 'min:2', 'max:100'],
            'last_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('bvnsdkform', 'email')],
            'phone_number' => ['required', 'string', 'regex:/^\d{11,}$/', Rule::unique('bvnsdkform', 'phoneNumber')],
            'address' => ['required', 'string', 'min:5', 'max:255'],
            'state_of_residence' => ['required', 'string', 'min:2', 'max:100'],
            'lga' => ['required', 'string', 'min:2', 'max:100'],
            'zone' => ['required', 'string', Rule::in(WebController::ZONES)],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
        ]);

        $user = $request->user();
        $oldBalance = (float) $user->balance;

        [$price, $refusal] = $this->charge($user, self::SERVICE, 'bvn_onboarding');

        if ($refusal) {
            return $refusal;
        }

        try {
            $record = BvnSdkForm::create([
                'agentLocation' => $validated['agent_location'],
                'agentBvn' => $validated['agent_bvn'],
                'bankName' => $validated['bank_name'],
                'accountNumber' => $validated['account_number'],
                'accountName' => $validated['account_name'],
                'firstName' => $validated['first_name'],
                'lastName' => $validated['last_name'],
                'email' => $validated['email'],
                'phoneNumber' => $validated['phone_number'],
                'address' => $validated['address'],
                'stateOfResidence' => $validated['state_of_residence'],
                'lga' => $validated['lga'],
                'zone' => $validated['zone'],
                'dateOfBirth' => $validated['date_of_birth'],
                'oldBal' => (string) $oldBalance,
                'newBal' => (string) $user->balance,
                'status' => 'Submitted',
                'userId' => $user->id,
            ]);
        } catch (\Throwable $e) {
            $this->refund($user, $price);
            report($e);

            return $this->error('submission_failed', 'The registration could not be filed. You were not charged.', 502);
        }

        return $this->accepted($record, $price, $this->present($record));
    }

    /**
     * The caller's own registrations, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BvnSdkForm::where('userId', $request->user()->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return $this->listing($query, fn (BvnSdkForm $f) => $this->present($f), (int) $request->input('limit', 50));
    }

    /**
     * One registration, by the id we returned or by the agent's email.
     */
    public function show(Request $request, string $submission): JsonResponse
    {
        $query = BvnSdkForm::where('userId', $request->user()->id);

        // Email is unique across onboardings, so it identifies exactly one.
        $record = str_contains($submission, '@')
            ? $query->where('email', $submission)->first()
            : $query->find($submission);

        if (! $record) {
            return $this->error('not_found', 'No onboarding registration found for that id.', 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->present($record),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(BvnSdkForm $f): array
    {
        return [
            'id' => $f->id,
            'status' => $f->status,
            'comment' => $f->comment,
            'agent_location' => $f->agentLocation,
            'agent_bvn' => $f->agentBvn,
            'bank_name' => $f->bankName,
            'account_number' => $f->accountNumber,
            'account_name' => $f->accountName,
            'first_name' => $f->firstName,
            'last_name' => $f->lastName,
            'email' => $f->email,
            'phone_number' => $f->phoneNumber,
            'address' => $f->address,
            'state_of_residence' => $f->stateOfResidence,
            'lga' => $f->lga,
            'zone' => $f->zone,
            'date_of_birth' => $f->dateOfBirth?->toDateString(),
            'submitted_at' => $f->createdAt?->toIso8601String(),
            'updated_at' => $f->updatedAt?->toIso8601String(),
        ];
    }
}
