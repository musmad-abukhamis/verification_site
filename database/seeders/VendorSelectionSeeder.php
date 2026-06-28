<?php

namespace Database\Seeders;

use App\Models\VendorSelection;
use Illuminate\Database\Seeder;

/**
 * Active vendor (1-5) per network + service type (ported from production).
 *
 * id is stored uppercase because getActiveVendorNumber() looks it up via
 * strtoupper($network). Every service column points at vendor 1 (bozavtu).
 */
class VendorSelectionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['MTN', 'AIRTEL', 'GLO', '9MOBILE'] as $network) {
            VendorSelection::updateOrCreate(['id' => $network], [
                'SME' => '1',
                'SME2' => '1',
                'CORPORATE_GIFTING' => '1',
                'CORPORATE_GIFTING2' => '1',
                'DATASHARE' => '1',
            ]);
        }
    }
}
