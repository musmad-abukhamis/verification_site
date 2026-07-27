<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanVendorMapping;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Real MTN data plan catalogue (ported from production `abcweb`), now on the
 * normalized schema. Each plan's per-vendor external code lives in
 * plan_vendor_mappings against the bozavtu vendor (the only live one).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // id, name, price, agent_price, api_price, type, validity, bozavtu external code
        $plans = [
            ['id' => 3, 'name' => '1GB',   'price' => 700,  'agent_price' => 1500, 'api_price' => 1500, 'type' => 'SME',       'validity' => '30 Days', 'external' => '2'],
            ['id' => 4, 'name' => '500MB', 'price' => 470,  'agent_price' => 470,  'api_price' => 470,  'type' => 'DATASHARE', 'validity' => '7 Days',  'external' => '45'],
            ['id' => 5, 'name' => '1GB',   'price' => 600,  'agent_price' => 650,  'api_price' => 650,  'type' => 'DATASHARE', 'validity' => '7 Days',  'external' => '35'],
            ['id' => 6, 'name' => '2GB',   'price' => 1400, 'agent_price' => 1500, 'api_price' => 1500, 'type' => 'DATASHARE', 'validity' => '30 Days', 'external' => '43'],
            ['id' => 7, 'name' => '3GB',   'price' => 2100, 'agent_price' => 2200, 'api_price' => 2200, 'type' => 'DATASHARE', 'validity' => '30 Days', 'external' => '42'],
            ['id' => 8, 'name' => '5GB',   'price' => 3200, 'agent_price' => 3300, 'api_price' => 3300, 'type' => 'DATASHARE', 'validity' => '30 Days', 'external' => '44'],
            ['id' => 9, 'name' => '1.0',   'price' => 650,  'agent_price' => 650,  'api_price' => 650,  'type' => 'SME',       'validity' => '30 Days', 'external' => '54'],
        ];

        $bozavtu = Vendor::where('name', 'bozavtu')->first();

        foreach ($plans as $row) {
            $external = $row['external'];
            unset($row['external']);

            Plan::updateOrCreate(['id' => $row['id']], array_merge($row, [
                'network' => 'mtn',
                'status' => 'on',
                'plan_status' => 'on',
            ]));

            if ($bozavtu) {
                PlanVendorMapping::updateOrCreate(
                    ['plan_id' => $row['id'], 'vendor_id' => $bozavtu->id],
                    ['external_plan_id' => $external],
                );
            }
        }

        // Keep Postgres' identity sequence ahead of the explicit ids above.
        DB::statement("SELECT setval(pg_get_serial_sequence('plans', 'id'), (SELECT MAX(id) FROM plans))");
    }
}
