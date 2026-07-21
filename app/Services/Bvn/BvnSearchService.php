<?php

namespace App\Services\Bvn;

use App\Models\NinDetail;
use App\Models\ServicePrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BVN lookup: charge, call the provider, log the attempt, refund on failure.
 *
 * Extracted from BvnSearchController so the web UI and the reseller API run the
 * same code -- including the same role-based price and the same refund on a
 * failed lookup. Attempts land in NINDetails with idtype = "bvn".
 */
class BvnSearchService
{
    /** slipType => service_prices key (premium = BVN Slip). */
    public const SLIP_SERVICES = [
        'premium' => 'bvn.search.premium',
        'standard' => 'bvn.search.standard',
        'regular' => 'bvn.search.regular',
    ];

    /**
     * What this user pays for a slip type, or null when unavailable.
     */
    public function price(string $slipType, ?User $user): ?float
    {
        $service = self::SLIP_SERVICES[$slipType] ?? null;

        return $service ? ServicePrice::priceForUser($service, $user) : null;
    }

    /**
     * Slip types the user can buy, at the price they would pay.
     */
    public function activeSlipTypes(?User $user): array
    {
        $labels = ['premium' => 'BVN Slip', 'standard' => 'Standard Slip', 'regular' => 'Regular Slip'];

        $types = [];

        foreach (self::SLIP_SERVICES as $code => $service) {
            $price = $this->price($code, $user);

            if ($price !== null) {
                $types[] = ['code' => $code, 'name' => $labels[$code], 'price' => $price];
            }
        }

        return $types;
    }

    /**
     * Run a lookup.
     *
     * @return array{success: bool, message?: string, data?: array, reference: string, price?: float, code?: string}
     */
    public function search(User $user, string $bvn, string $slipType): array
    {
        $price = $this->price($slipType, $user);
        $reference = 'Verify_'.now()->timestamp.random_int(1000, 9999);

        if ($price === null) {
            return [
                'success' => false,
                'code' => 'service_unavailable',
                'message' => 'This service is currently unavailable. Please contact support.',
                'reference' => $reference,
            ];
        }

        $oldBalance = (float) $user->balance;

        if ($oldBalance < $price) {
            return [
                'success' => false,
                'code' => 'insufficient_balance',
                'message' => 'Insufficient balance. Please top up your account.',
                'reference' => $reference,
                'price' => $price,
            ];
        }

        try {
            DB::beginTransaction();

            if (! $user->debit($price, false, ['fundingtype' => 'bvn_search'])) {
                DB::rollBack();

                return [
                    'success' => false,
                    'code' => 'insufficient_balance',
                    'message' => 'Insufficient balance. Please top up your account.',
                    'reference' => $reference,
                    'price' => $price,
                ];
            }

            $base = rtrim((string) config('services.arewasmart.base_url'), '/');

            $response = Http::timeout(40)
                ->withToken((string) config('services.arewasmart.token'))
                ->acceptJson()
                ->post($base.'/bvn/verify', ['bvn' => $bvn]);

            $body = $response->json();
            $data = $body['data'] ?? null;

            if ($response->successful() && ($body['status'] ?? null) === 'success' && is_array($data)) {
                $details = $this->normalize($data, $bvn);

                NinDetail::create([
                    'id' => $reference,
                    'surname' => $details['surname'],
                    'othernames' => trim(($details['firstname'] ?? '').' '.($details['middlename'] ?? '')) ?: null,
                    'idtype' => 'bvn',
                    'idvalue' => $bvn,
                    'sliptype' => $slipType,
                    'status' => 'success',
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'price' => (int) round($price),
                    'userId' => $user->id,
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'data' => $details,
                    'reference' => $reference,
                    'price' => $price,
                ];
            }

            // Provider said no: refund and log the attempt.
            $this->refund($user, $price);

            NinDetail::create([
                'id' => $reference,
                'idtype' => 'bvn',
                'idvalue' => $bvn,
                'sliptype' => $slipType,
                'status' => 'fail',
                'oldBal' => $oldBalance,
                'newBal' => $oldBalance,
                'price' => (int) round($price),
                'userId' => $user->id,
            ]);

            DB::commit();

            $message = $body['message'] ?? $body['error'] ?? 'Verification failed. Please check the BVN and try again.';

            return [
                'success' => false,
                'code' => 'verification_failed',
                'message' => is_array($message) ? implode(' ', $message) : $message,
                'reference' => $reference,
                'price' => $price,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->refund($user, $price);
            Log::error('BVN search error: '.$e->getMessage());

            return [
                'success' => false,
                'code' => 'provider_error',
                'message' => 'Network error. Please try again.',
                'reference' => $reference,
                'price' => $price,
            ];
        }
    }

    private function refund(User $user, float $price): void
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
