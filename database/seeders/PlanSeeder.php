<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Plan::create([
            'network' => 'mtn',
            'name' => 'MTN SME 1GB',
            'price' => 300,
            'agentPrice' => 280,
            'apiPrice' => 270,
            'type' => 'sme',
            'validity' => '30 days',
            'status' => 'on',
            'planStatus' => 'on',
            'apiKey' => 'mtn-api-key-1',
            'vendorPlan1' => 'mtn-sme-1gb',
            'vendorPlan2' => 'MTN-SME-1GB',
            'vendorPlan3' => 'mtn_sme_1gb',
            'vendorPlan4' => 'MTN_SME_1GB',
            'vendorPlan5' => 'mtn-sme-1gb-v5'
        ]);

        Plan::create([
            'network' => 'airtel',
            'name' => 'Airtel SME 1GB',
            'price' => 250,
            'agentPrice' => 240,
            'apiPrice' => 230,
            'type' => 'sme',
            'validity' => '30 days',
            'status' => 'on',
            'planStatus' => 'on',
            'apiKey' => 'airtel-api-key-1',
            'vendorPlan1' => 'airtel-sme-1gb',
            'vendorPlan2' => 'AIRTEL-SME-1GB',
            'vendorPlan3' => 'airtel_sme_1gb',
            'vendorPlan4' => 'AIRTEL_SME_1GB',
            'vendorPlan5' => 'airtel-sme-1gb-v5'
        ]);

        Plan::create([
            'network' => 'glo',
            'name' => 'Glo Direct 2GB',
            'price' => 350,
            'agentPrice' => 330,
            'apiPrice' => 320,
            'type' => 'direct',
            'validity' => '30 days',
            'status' => 'on',
            'planStatus' => 'on',
            'apiKey' => 'glo-api-key-1',
            'vendorPlan1' => 'glo-direct-2gb',
            'vendorPlan2' => 'GLO-DIRECT-2GB',
            'vendorPlan3' => 'glo_direct_2gb',
            'vendorPlan4' => 'GLO_DIRECT_2GB',
            'vendorPlan5' => 'glo-direct-2gb-v5'
        ]);

        Plan::create([
            'network' => '9mobile',
            'name' => '9mobile Direct 2GB',
            'price' => 400,
            'agentPrice' => 380,
            'apiPrice' => 370,
            'type' => 'direct',
            'validity' => '30 days',
            'status' => 'on',
            'planStatus' => 'on',
            'apiKey' => '9mobile-api-key-1',
            'vendorPlan1' => '9mobile-direct-2gb',
            'vendorPlan2' => '9MOBILE-DIRECT-2GB',
            'vendorPlan3' => '9mobile_direct_2gb',
            'vendorPlan4' => '9MOBILE_DIRECT_2GB',
            'vendorPlan5' => '9mobile-direct-2gb-v5'
        ]);
    }
}