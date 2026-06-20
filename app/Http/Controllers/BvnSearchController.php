<?php

namespace App\Http\Controllers;

use App\Models\BvnServicePrice;
use App\Models\NinDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    /** slipType -> bvnserviceprices column (premium = BVN Slip). */
    private array $slipColumns = [
        'premium' => 'searchslip1',
        'standard' => 'searchslip2',
        'regular' => 'searchslip3',
    ];

    private function walletPayload($user): array
    {
        $balance = (float) $user->balance;

        return [
            'balance' => $balance,
            'bonus_balance' => 0.0,
            'total_balance' => $balance,
        ];
    }

    private function prices(): BvnServicePrice
    {
        return BvnServicePrice::firstOrCreate(['id' => 'API1']);
    }

    private function slipPrice(string $slipType): ?float
    {
        $column = $this->slipColumns[$slipType] ?? null;
        if (! $column) {
            return null;
        }

        $value = $this->prices()->{$column};

        return ($value === null || $value === '' || ! is_numeric($value)) ? null : (float) $value;
    }

    /**
     * Slip types that have a configured price, for the frontend selector.
     */
    private function activeSlipTypes(): array
    {
        $labels = ['premium' => 'BVN Slip', 'standard' => 'Standard Slip', 'regular' => 'Regular Slip'];

        $types = [];
        foreach ($this->slipColumns as $code => $column) {
            $price = $this->slipPrice($code);
            if ($price !== null && $price > 0) {
                $types[] = ['code' => $code, 'name' => $labels[$code], 'price' => $price];
            }
        }

        return $types;
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
            'slipTypes' => $this->activeSlipTypes(),
            'history' => $history,
        ]);
    }

    public function searchV1(Request $request)
    {
        return $this->process($request, 'v1');
    }

    public function searchV2(Request $request)
    {
        return $this->process($request, 'v2');
    }

    protected function process(Request $request, string $version)
    {
        $validated = $request->validate([
            'idValue' => 'required|digits:11',
            'slipType' => 'required|string|in:'.implode(',', array_keys($this->slipColumns)),
        ]);

        $slipType = $validated['slipType'];
        $idValue = $validated['idValue'];

        $price = $this->slipPrice($slipType);
        if ($price === null) {
            return back()->withErrors(['message' => 'Service configuration error. Please contact support.']);
        }

        $user = Auth::user();
        $oldBalance = (float) $user->balance;

        if ($oldBalance < $price) {
            return back()->withErrors(['message' => 'Insufficient balance. Please top up your account.']);
        }

        $reference = 'Verify_'.now()->timestamp.random_int(1000, 9999);

        try {
            DB::beginTransaction();

            if (! $user->debit($price, false, ['fundingtype' => 'bvn_search'])) {
                DB::rollBack();

                return back()->withErrors(['message' => 'Insufficient balance. Please top up your account.']);
            }

            $base = rtrim((string) config('services.arewasmart.base_url'), '/');

            $response = Http::timeout(40)
                ->withToken((string) config('services.arewasmart.token')) // Authorization: Bearer <token>
                ->acceptJson()
                ->post($base.'/bvn/verify', [
                    'bvn' => $idValue,
                ]);

            $body = $response->json();
            $data = $body['data'] ?? null;

            if ($response->successful() && ($body['status'] ?? null) === 'success' && is_array($data)) {
                $details = $this->normalize($data, $idValue);

                NinDetail::create([
                    'id' => $reference,
                    'surname' => $details['surname'],
                    'othernames' => trim(($details['firstname'] ?? '').' '.($details['middlename'] ?? '')) ?: null,
                    'idtype' => 'bvn',
                    'idvalue' => $idValue,
                    'sliptype' => $slipType,
                    'status' => 'success',
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'price' => (int) round($price),
                    'userId' => $user->id,
                ]);

                DB::commit();

                return back()->with([
                    'success' => 'BVN details fetched successfully.',
                    'verification_data' => $details,
                ]);
            }

            // Failure: refund and log.
            $this->refund($user, $price);

            NinDetail::create([
                'id' => $reference,
                'idtype' => 'bvn',
                'idvalue' => $idValue,
                'sliptype' => $slipType,
                'status' => 'fail',
                'oldBal' => $oldBalance,
                'newBal' => $oldBalance,
                'price' => (int) round($price),
                'userId' => $user->id,
            ]);

            DB::commit();

            $message = $body['message'] ?? $body['error'] ?? 'Verification failed. Please check the BVN and try again.';
            if (is_array($message)) {
                $message = implode(' ', $message);
            }

            return back()->withErrors(['message' => $message]);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->refund($user, $price);
            Log::error("BVN search {$version} error: ".$e->getMessage());

            return back()->withErrors(['message' => 'Network error. Please try again.']);
        }
    }

    private function refund($user, float $price): void
    {
        $user->credit($price, false, ['fundingtype' => 'refund', 'status' => 'refund']);
    }

    /**
     * Map the ArewaSmart BVN `data` (camelCase) into the field names the slip
     * component renders. `photo` is raw base64 JPEG (no data: prefix).
     */
    private function normalize(array $d, string $bvn): array
    {
        return [
            'bvn' => $d['bvn'] ?? $bvn,
            'surname' => $d['lastName'] ?? null,
            'firstname' => $d['firstName'] ?? null,
            'middlename' => $d['middleName'] ?? null,
            'gender' => $d['gender'] ?? null,
            'dob' => $d['birthday'] ?? null,
            'phone' => $d['phoneNumber'] ?? null,
            'phone2' => $d['phoneNumber2'] ?? null,
            'email' => $d['email'] ?? null,
            'photo' => $d['photo'] ?? null,
            'marital_status' => $d['maritalStatus'] ?? null,
            'state_of_origin' => $d['stateOfOrigin'] ?? null,
            'lga_of_origin' => $d['lgaOfOrigin'] ?? null,
            'registration_date' => $d['registrationDate'] ?? null,
            'enrollment_bank' => $d['enrollmentBank'] ?? null,
            'enrollment_bank_branch' => $d['enrollmentBranch'] ?? null,
            'residential_Address' => $d['residentialAddress'] ?? null,
            'nationality' => $d['nationality'] ?? null,
        ];
    }
}
