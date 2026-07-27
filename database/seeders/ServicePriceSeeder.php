<?php

namespace Database\Seeders;

use App\Models\ServicePrice;
use Illuminate\Database\Seeder;

/**
 * Seeds the base price of every service.
 *
 * A service with no base row is unavailable to everyone, so without this a
 * fresh install has a NIN section where nothing can be bought. Idempotent:
 * only creates rows that are missing, so admin-set prices survive a re-run.
 *
 * Replaces VerifyApiConfigSeeder, which seeded verifyapiconfiq -- a table
 * nothing reads since pricing moved to service_prices.
 */
class ServicePriceSeeder extends Seeder
{
    private const DEFAULTS = [
        'nin.verify' => 50,
        'nin.phone' => 100,
        'nin.demographic' => 100,
        'nin.ipe' => 50,
        'nin.validation' => 50,
        'slip.regular' => 50,
        'slip.standard' => 100,
        'slip.premium' => 150,
        'slip.nvs' => 200,
        'slip.advanced' => 250,
        'bvn.mod.name' => 2000,
        'bvn.mod.dob' => 2000,
        'bvn.mod.phone' => 2000,
        'bvn.mod.email' => 2000,
        'bvn.mod.name_dob' => 3000,
        'bvn.mod.name_phone' => 3000,
        'bvn.mod.name_dob_phone' => 4000,
        'bvn.search.premium' => 150,
        'bvn.search.standard' => 100,
        'bvn.search.regular' => 50,
        'bvn.retrieve.phone' => 500,
        'bvn.retrieve.id' => 500,
        'bvn.onboarding1' => 1000,
        'bvn.onboarding2' => 1000,
        'bvn.idcard' => 1000,
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $service => $price) {
            ServicePrice::firstOrCreate(
                ['service' => $service, 'role' => ServicePrice::BASE],
                ['price' => $price, 'is_active' => true],
            );
        }

        ServicePrice::forgetCache();
    }
}
