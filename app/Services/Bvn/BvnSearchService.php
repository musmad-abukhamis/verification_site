<?php

namespace App\Services\Bvn;

use App\Models\NinDetail;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\Verification\VerificationDispatcher;
use App\Support\BankDirectory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BVN lookup: charge, call the provider, log the attempt, refund on failure.
 *
 * Extracted from BvnSearchController so the web UI and the reseller API run the
 * same code -- including the same role-based price and the same refund on a
 * failed lookup. Attempts land in NINDetails with idtype = "bvn".
 *
 * The provider comes from the `bvn.verify` chain configured in
 * Admin > Verification, so this honours the routing/failover order and every
 * call shows up in Provider Calls. Slip pricing is unchanged -- it is still per
 * slip type (bvn.search.*), not the engine's service price.
 */
class BvnSearchService
{
    public function __construct(private readonly VerificationDispatcher $dispatcher) {}

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

            $lookup = $this->lookup($user, $bvn, $reference);

            if ($lookup['success']) {
                $details = $lookup['data'];

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

            return [
                'success' => false,
                'code' => 'verification_failed',
                'message' => $lookup['message'] ?? 'Verification failed. Please check the BVN and try again.',
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
     * Run the lookup against the routed `bvn.verify` chain.
     *
     * @return array{success: bool, data?: array<string, mixed>, message?: string}
     */
    private function lookup(User $user, string $bvn, string $reference): array
    {
        // No silent fallback to a config-file provider: if nothing is routed for
        // bvn.verify the request is refused (and refunded). A hidden fallback
        // would send customers to a provider the admin never selected and log
        // nothing in Provider Calls -- exactly the surprise this engine exists
        // to prevent.
        $outcome = $this->dispatcher->verify('bvn.verify', ['bvn' => $bvn], [
            'user_id' => $user->id,
            'reference' => $reference,
        ]);

        if (! $outcome->isSuccess()) {
            return ['success' => false, 'message' => $outcome->message];
        }

        return ['success' => true, 'data' => $this->toSlipFields($outcome->data, $bvn)];
    }

    /**
     * The engine's canonical field names -> the names the slip component
     * renders. Kept as a translation layer so the Vue slip templates did not
     * have to change.
     *
     * @param  array<string, mixed>  $d  canonical, from ResponseNormalizer
     * @return array<string, mixed>
     */
    private function toSlipFields(array $d, string $bvn): array
    {
        return [
            'bvn' => $d['bvn'] ?? $bvn,
            'surname' => $d['last_name'] ?? null,
            'firstname' => $d['first_name'] ?? null,
            'middlename' => $d['middle_name'] ?? null,
            'gender' => $d['gender'] ?? null,
            'dob' => $d['date_of_birth'] ?? null,
            'phone' => $d['phone'] ?? null,
            'phone2' => $d['phone2'] ?? null,
            'email' => $d['email'] ?? null,
            'photo' => $d['photo'] ?? null,
            'marital_status' => $d['marital_status'] ?? null,
            'state_of_origin' => $d['state_of_origin'] ?? null,
            'lga_of_origin' => $d['lga_of_origin'] ?? null,
            'registration_date' => $d['registration_date'] ?? null,
            // Providers report these as CBN codes; the slip needs names.
            //
            // The branch deliberately resolves against the same institution
            // table: in the records we get, the branch field carries an
            // institution code rather than a branch-level one. The trade-off is
            // known -- a genuine branch code that happens to collide with a bank
            // code will read as that bank's name. If branch codes ever arrive in
            // their own numbering, this needs its own table, not this one.
            'enrollment_bank' => BankDirectory::name($d['enrollment_bank'] ?? null),
            'enrollment_bank_branch' => BankDirectory::name($d['enrollment_bank_branch'] ?? null),
            // Capital A retained: the slip components already bind this name.
            'residential_Address' => $d['residence_address'] ?? null,
            'nationality' => $d['nationality'] ?? null,
        ];
    }

}
