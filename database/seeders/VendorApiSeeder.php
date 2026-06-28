<?php

namespace Database\Seeders;

use App\Models\VendorApi;
use Illuminate\Database\Seeder;

/**
 * Vendor API endpoints/keys for data vending (single row, id = 1).
 *
 * Only vendor 1 (bozavtu) is live; its URL + key come from config/env so the
 * secret stays out of source control (see config('services.data_vendors')).
 * Vendors 2-5 are inert placeholders matching production.
 */
class VendorApiSeeder extends Seeder
{
    public function run(): void
    {
        VendorApi::updateOrCreate(['id' => 1], [
            'vendor1url' => config('services.data_vendors.vendor1_url'),
            'vendor1key' => (string) config('services.data_vendors.vendor1_key'),
            'vendor2url' => '0', 'vendor2key' => '0',
            'vendor3url' => '0', 'vendor3key' => '0',
            'vendor4url' => '0', 'vendor4key' => '0',
            'vendor5url' => '0', 'vendor5key' => '0',
        ]);
    }
}
