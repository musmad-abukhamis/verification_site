<?php

namespace App\Http\Controllers\Concerns;

use App\Models\BvnModification;
use App\Models\ServicePrice;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Shared BVN-modification service-type metadata and pricing helpers.
 *
 * Prices come from service_prices and depend on the user's role, the same as
 * the NIN services. They were previously read straight off the single-row
 * bvnserviceprices config, which had exactly one price per service.
 */
trait BvnModificationPricing
{
    /**
     * Service types exposed by the submission form.
     */
    protected function serviceTypes(): array
    {
        return [
            'modify-name',
            'modify-dob',
            'modify-name-dob',
            'modify-phone',
            'modify-name-dob-phone',
        ];
    }

    /**
     * serviceType → service_prices key.
     */
    protected function priceColumn(string $serviceType): ?string
    {
        return match ($serviceType) {
            'modify-name' => 'bvn.mod.name',
            'modify-phone' => 'bvn.mod.phone',
            'modify-dob' => 'bvn.mod.dob',
            'modify-email' => 'bvn.mod.email',
            'modify-name-dob' => 'bvn.mod.name_dob',
            'modify-name-phone' => 'bvn.mod.name_phone',
            'modify-name-dob-phone' => 'bvn.mod.name_dob_phone',
            default => null,
        };
    }

    /**
     * Human label for a service type.
     */
    protected function serviceLabel(string $serviceType): string
    {
        return match ($serviceType) {
            'modify-name' => 'Name Modification',
            'modify-dob' => 'DOB Modification',
            'modify-name-dob' => 'Name & DOB Modification',
            'modify-phone' => 'Phone Number Modification',
            'modify-name-dob-phone' => 'Name, DOB & Phone Modification',
            'modify-email' => 'Email Modification',
            'modify-name-phone' => 'Name & Phone Modification',
            default => $serviceType,
        };
    }

    protected function needsName(string $serviceType): bool
    {
        return in_array($serviceType, ['modify-name', 'modify-name-dob', 'modify-name-dob-phone'], true);
    }

    protected function needsDob(string $serviceType): bool
    {
        return in_array($serviceType, ['modify-dob', 'modify-name-dob', 'modify-name-dob-phone'], true);
    }

    protected function needsPhone(string $serviceType): bool
    {
        return in_array($serviceType, ['modify-phone', 'modify-name-dob-phone'], true);
    }

    /**
     * What this user pays for a service type, or null when it is unavailable.
     * Defaults to the logged-in user, whose role selects the price.
     */
    protected function servicePrice(string $serviceType, ?User $user = null): ?float
    {
        $service = $this->priceColumn($serviceType);

        return $service
            ? ServicePrice::priceForUser($service, $user ?? Auth::user())
            : null;
    }

    /**
     * Price map keyed by the raw column names the frontend reads, priced for
     * the current user. The keys are kept as-is so the Vue pages do not have to
     * change; only where the numbers come from has.
     */
    protected function pricePayload(?User $user = null): array
    {
        $user ??= Auth::user();

        $columns = [
            'name_mod' => 'modify-name',
            'dob_mod' => 'modify-dob',
            'phone_mod' => 'modify-phone',
            'email_mod' => 'modify-email',
            'namedob_mod' => 'modify-name-dob',
            'namephone_mod' => 'modify-name-phone',
            'namephonedob_mod' => 'modify-name-dob-phone',
        ];

        return array_map(
            fn (string $serviceType) => $this->servicePrice($serviceType, $user),
            $columns,
        );
    }

    /**
     * Full detail payload for a single request (used by user + admin show pages).
     */
    protected function detailPayload(BvnModification $r): array
    {
        return [
            'id' => $r->id,
            'bvn' => $r->bvn,
            'nin' => $r->nin,
            'serviceType' => $r->serviceType,
            'service_label' => $this->serviceLabel($r->serviceType),
            'status' => $r->status,
            'comment' => $r->comment,
            'amount_charged' => $r->amountCharged,
            'old_balance' => $r->oldBal,
            'new_balance' => $r->newBal,
            'needs_name' => $this->needsName($r->serviceType),
            'needs_dob' => $this->needsDob($r->serviceType),
            'needs_phone' => $this->needsPhone($r->serviceType),
            'old_first_name' => $r->oldFirstName,
            'old_middle_name' => $r->oldMiddleName,
            'old_last_name' => $r->oldLastName,
            'old_dob' => $r->oldDob,
            'old_phone_number' => $r->oldPhoneNumber,
            'new_first_name' => $r->newFirstName,
            'new_middle_name' => $r->newMiddleName,
            'new_last_name' => $r->newLastName,
            'new_dob' => $r->newDob,
            'new_phone_number' => $r->newPhoneNumber,
            'created_at' => $r->createdAt,
            'updated_at' => $r->updatedAt,
            'user' => $r->relationLoaded('user') && $r->user ? [
                'id' => $r->user->id,
                'name' => $r->user->name,
                'username' => $r->user->username,
                'email' => $r->user->email,
            ] : null,
        ];
    }
}
