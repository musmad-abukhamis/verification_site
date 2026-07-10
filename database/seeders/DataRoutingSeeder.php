<?php

namespace Database\Seeders;

use App\Models\NetworkVendorMapping;
use App\Models\Vendor;
use App\Models\VendorRoute;
use Illuminate\Database\Seeder;

/**
 * Maps each network to the bozavtu vendor's external network code and installs
 * a position-1 route per (network, type). Only MTN is live today; the other
 * networks are wired so the admin routing matrix has sane defaults.
 */
class DataRoutingSeeder extends Seeder
{
    public function run(): void
    {
        $bozavtu = Vendor::where('name', 'bozavtu')->first();

        if (! $bozavtu) {
            return;
        }

        // bozavtu network codes (mtn = 1 mirrors the old networks default).
        $networkCodes = ['mtn' => '1', 'airtel' => '2', 'glo' => '3', '9mobile' => '4'];

        foreach ($networkCodes as $network => $code) {
            NetworkVendorMapping::updateOrCreate(
                ['network' => $network, 'vendor_id' => $bozavtu->id],
                ['external_network_code' => $code],
            );
        }

        // Route the live data types (SME, DATASHARE) through bozavtu at position 1.
        foreach (array_keys($networkCodes) as $network) {
            foreach (['SME', 'DATASHARE'] as $type) {
                VendorRoute::updateOrCreate(
                    ['network' => $network, 'type' => $type, 'position' => 1],
                    ['vendor_id' => $bozavtu->id],
                );
            }
        }
    }
}
