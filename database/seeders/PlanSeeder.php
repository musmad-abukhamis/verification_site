<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Real MTN data plan catalogue (ported from the production `abcweb` DB).
 *
 * `network` is stored lowercase because Plan::byNetwork() compares against the
 * lowercased form value; `type` (SME/DATASHARE) is preserved as-is because it
 * maps to the vendorselection columns. Only vendorPlan1 (bozavtu) is real —
 * vendors 2-5 are unused placeholders.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['id' => 3, 'name' => '1GB',   'price' => 700,  'agentPrice' => 1500, 'apiPrice' => 1500, 'type' => 'SME',       'validity' => '30 Days', 'apiKey' => null, 'vendorPlan1' => '2'],
            ['id' => 4, 'name' => '500MB', 'price' => 470,  'agentPrice' => 470,  'apiPrice' => 470,  'type' => 'DATASHARE', 'validity' => '7 Days',  'apiKey' => 1,    'vendorPlan1' => '45'],
            ['id' => 5, 'name' => '1GB',   'price' => 600,  'agentPrice' => 650,  'apiPrice' => 650,  'type' => 'DATASHARE', 'validity' => '7 Days',  'apiKey' => 2,    'vendorPlan1' => '35'],
            ['id' => 6, 'name' => '2GB',   'price' => 1400, 'agentPrice' => 1500, 'apiPrice' => 1500, 'type' => 'DATASHARE', 'validity' => '30 Days', 'apiKey' => 3,    'vendorPlan1' => '43'],
            ['id' => 7, 'name' => '3GB',   'price' => 2100, 'agentPrice' => 1500, 'apiPrice' => 1500, 'type' => 'DATASHARE', 'validity' => '30 Days', 'apiKey' => 4,    'vendorPlan1' => '42'],
            ['id' => 8, 'name' => '5GB',   'price' => 3200, 'agentPrice' => 23,   'apiPrice' => 1500, 'type' => 'DATASHARE', 'validity' => '30 Days', 'apiKey' => 5,    'vendorPlan1' => '44'],
            ['id' => 9, 'name' => '1.0',   'price' => 650,  'agentPrice' => 650,  'apiPrice' => 650,  'type' => 'SME',       'validity' => '30 Days', 'apiKey' => 6,    'vendorPlan1' => '54'],
        ];

        // Establish exactly the production catalogue (drop stale sample rows).
        Plan::query()->delete();

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['id' => $plan['id']], array_merge($plan, [
                'network' => 'mtn',
                'status' => 'on',
                'planStatus' => 'on',
                'vendorPlan2' => '0',
                'vendorPlan3' => '0',
                'vendorPlan4' => '0',
                'vendorPlan5' => '0',
            ]));
        }

        // Keep Postgres' identity sequence ahead of the explicit ids above.
        DB::statement('SELECT setval(pg_get_serial_sequence(\'"Plan"\', \'id\'), (SELECT MAX(id) FROM "Plan"))');
    }
}
