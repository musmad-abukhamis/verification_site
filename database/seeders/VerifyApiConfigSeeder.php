<?php

namespace Database\Seeders;

use App\Models\VerifyApiConfig;
use Illuminate\Database\Seeder;

/**
 * Seeds the single-row verifyapiconfiq pricing.
 *
 * Slip types only appear in the verification UI when their price is > 0
 * (see SlipDownloadService::getActiveSlipTypes), so without these values no
 * slips can be downloaded after a verification. Idempotent: safe to re-run,
 * and only fills prices that are still null so admin-set values are preserved.
 */
class VerifyApiConfigSeeder extends Seeder
{
    public function run(): void
    {
        $config = VerifyApiConfig::firstOrCreate(['id' => 'API1']);

        $defaults = [
            'regslipprice' => 50,
            'standardslipsprice' => 100,
            'premiumslipprice' => 150,
            'nvsslipprice' => 200,
            'advslipprice' => 250,
            'ipeprice' => 50,
            'validation' => 50,
            'status' => 'Active',
        ];

        foreach ($defaults as $column => $value) {
            if ($config->{$column} === null) {
                $config->{$column} = $value;
            }
        }

        $config->save();
    }
}
