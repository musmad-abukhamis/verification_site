<?php

namespace App\Http\Controllers\Concerns;

use App\Models\BvnModification;
use App\Models\BvnServicePrice;

/**
 * Shared BVN-modification service-type metadata and pricing helpers.
 *
 * Mirrors the serviceType → price column mapping from the nimcweb source
 * (bvnserviceprices) and the labels used across its pages.
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
     * serviceType → bvnserviceprices column.
     */
    protected function priceColumn(string $serviceType): ?string
    {
        return match ($serviceType) {
            'modify-name' => 'name_mod',
            'modify-phone' => 'phone_mod',
            'modify-dob' => 'dob_mod',
            'modify-email' => 'email_mod',
            'modify-name-dob' => 'namedob_mod',
            'modify-name-phone' => 'namephone_mod',
            'modify-name-dob-phone' => 'namephonedob_mod',
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
     * The single-row pricing config (string id "API1").
     */
    protected function bvnPrices(): BvnServicePrice
    {
        return BvnServicePrice::firstOrCreate(['id' => 'API1']);
    }

    /**
     * Numeric price for a service type, or null when not configured.
     */
    protected function servicePrice(string $serviceType): ?float
    {
        $column = $this->priceColumn($serviceType);
        if (! $column) {
            return null;
        }

        $value = $this->bvnPrices()->{$column};
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Price map keyed by the raw bvnserviceprices columns the frontend reads.
     */
    protected function pricePayload(): array
    {
        $prices = $this->bvnPrices();

        return [
            'name_mod' => $prices->name_mod,
            'dob_mod' => $prices->dob_mod,
            'phone_mod' => $prices->phone_mod,
            'email_mod' => $prices->email_mod,
            'namedob_mod' => $prices->namedob_mod,
            'namephone_mod' => $prices->namephone_mod,
            'namephonedob_mod' => $prices->namephonedob_mod,
        ];
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
