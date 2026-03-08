<?php

namespace Database\Seeders;

use App\Models\DataPlan;
use Illuminate\Database\Seeder;

class DataPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            // MTN SME Data Plans
            ['network' => 'mtn', 'plan_type' => 'sme', 'name' => 'MTN SME 500MB', 'data_volume' => '500MB', 'price' => 150, 'agent_price' => 140, 'api_price' => 130, 'validity_days' => 30],
            ['network' => 'mtn', 'plan_type' => 'sme', 'name' => 'MTN SME 1GB', 'data_volume' => '1GB', 'price' => 280, 'agent_price' => 260, 'api_price' => 240, 'validity_days' => 30],
            ['network' => 'mtn', 'plan_type' => 'sme', 'name' => 'MTN SME 2GB', 'data_volume' => '2GB', 'price' => 550, 'agent_price' => 520, 'api_price' => 490, 'validity_days' => 30],
            ['network' => 'mtn', 'plan_type' => 'sme', 'name' => 'MTN SME 3GB', 'data_volume' => '3GB', 'price' => 800, 'agent_price' => 750, 'api_price' => 700, 'validity_days' => 30],
            ['network' => 'mtn', 'plan_type' => 'sme', 'name' => 'MTN SME 5GB', 'data_volume' => '5GB', 'price' => 1300, 'agent_price' => 1200, 'api_price' => 1100, 'validity_days' => 30],
            ['network' => 'mtn', 'plan_type' => 'sme', 'name' => 'MTN SME 10GB', 'data_volume' => '10GB', 'price' => 2500, 'agent_price' => 2300, 'api_price' => 2100, 'validity_days' => 30],

            // MTN Direct Data Plans
            ['network' => 'mtn', 'plan_type' => 'direct', 'name' => 'MTN 1.5GB', 'data_volume' => '1.5GB', 'price' => 1000, 'agent_price' => 950, 'api_price' => 900, 'validity_days' => 30],
            ['network' => 'mtn', 'plan_type' => 'direct', 'name' => 'MTN 2GB', 'data_volume' => '2GB', 'price' => 1200, 'agent_price' => 1140, 'api_price' => 1080, 'validity_days' => 30],
            ['network' => 'mtn', 'plan_type' => 'direct', 'name' => 'MTN 3GB', 'data_volume' => '3GB', 'price' => 1500, 'agent_price' => 1425, 'api_price' => 1350, 'validity_days' => 30],
            ['network' => 'mtn', 'plan_type' => 'direct', 'name' => 'MTN 5GB', 'data_volume' => '5GB', 'price' => 2500, 'agent_price' => 2375, 'api_price' => 2250, 'validity_days' => 30],

            // Glo Data Plans
            ['network' => 'glo', 'plan_type' => 'direct', 'name' => 'Glo 1.8GB', 'data_volume' => '1.8GB', 'price' => 500, 'agent_price' => 475, 'api_price' => 450, 'validity_days' => 14],
            ['network' => 'glo', 'plan_type' => 'direct', 'name' => 'Glo 3.9GB', 'data_volume' => '3.9GB', 'price' => 1000, 'agent_price' => 950, 'api_price' => 900, 'validity_days' => 30],
            ['network' => 'glo', 'plan_type' => 'direct', 'name' => 'Glo 7.5GB', 'data_volume' => '7.5GB', 'price' => 1500, 'agent_price' => 1425, 'api_price' => 1350, 'validity_days' => 30],
            ['network' => 'glo', 'plan_type' => 'direct', 'name' => 'Glo 9.2GB', 'data_volume' => '9.2GB', 'price' => 2000, 'agent_price' => 1900, 'api_price' => 1800, 'validity_days' => 30],

            // Airtel Data Plans
            ['network' => 'airtel', 'plan_type' => 'direct', 'name' => 'Airtel 1.5GB', 'data_volume' => '1.5GB', 'price' => 1000, 'agent_price' => 950, 'api_price' => 900, 'validity_days' => 30],
            ['network' => 'airtel', 'plan_type' => 'direct', 'name' => 'Airtel 2GB', 'data_volume' => '2GB', 'price' => 1200, 'agent_price' => 1140, 'api_price' => 1080, 'validity_days' => 30],
            ['network' => 'airtel', 'plan_type' => 'direct', 'name' => 'Airtel 3GB', 'data_volume' => '3GB', 'price' => 1500, 'agent_price' => 1425, 'api_price' => 1350, 'validity_days' => 30],
            ['network' => 'airtel', 'plan_type' => 'direct', 'name' => 'Airtel 4.5GB', 'data_volume' => '4.5GB', 'price' => 2000, 'agent_price' => 1900, 'api_price' => 1800, 'validity_days' => 30],

            // 9mobile Data Plans
            ['network' => '9mobile', 'plan_type' => 'direct', 'name' => '9mobile 1.5GB', 'data_volume' => '1.5GB', 'price' => 1000, 'agent_price' => 950, 'api_price' => 900, 'validity_days' => 30],
            ['network' => '9mobile', 'plan_type' => 'direct', 'name' => '9mobile 2GB', 'data_volume' => '2GB', 'price' => 1200, 'agent_price' => 1140, 'api_price' => 1080, 'validity_days' => 30],
            ['network' => '9mobile', 'plan_type' => 'direct', 'name' => '9mobile 3GB', 'data_volume' => '3GB', 'price' => 1500, 'agent_price' => 1425, 'api_price' => 1350, 'validity_days' => 30],
        ];

        // Clear existing data
        DataPlan::truncate();

        foreach ($plans as $plan) {
            DataPlan::create($plan);
        }
    }
}