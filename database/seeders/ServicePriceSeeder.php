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
