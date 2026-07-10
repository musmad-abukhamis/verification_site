<?php

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        // Live vendor — bozavtu (token-style A). Key stays out of source control.
        Vendor::updateOrCreate(
            ['name' => 'bozavtu'],
            [
                'base_url' => config('services.data_vendors.vendor1_url', 'https://vtu.bozavtu.com/api/data'),
                'driver' => 'token_style_a',
                'credentials' => ['key' => config('services.data_vendors.vendor1_key')],
                'is_active' => true,
                'priority' => 1,
            ],
        );

        // Sample inactive failover vendor (token-style B) — for admin/demo only.
        Vendor::updateOrCreate(
            ['name' => 'Sample Failover'],
            [
                'base_url' => 'https://example-vendor.test/api/data',
                'driver' => 'token_style_b',
                'credentials' => ['key' => 'demo-key'],
                'is_active' => false,
                'priority' => 2,
            ],
        );
    }
}
