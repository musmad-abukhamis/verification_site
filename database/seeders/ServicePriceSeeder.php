<?php

namespace Database\Seeders;

use App\Models\ServicePrice;
use Illuminate\Database\Seeder;

class ServicePriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prices = [
            [
                'service_type' => 'nin_verification',
                'name' => 'NIN Verification',
                'description' => 'Fee for verifying a NIN number',
                'price' => 50.00,
                'is_active' => true,
            ],
            [
                'service_type' => 'nin_ipe_submission',
                'name' => 'NIN IPE Submission',
                'description' => 'Fee for NIN IPE clearance submission',
                'price' => 50.00,
                'is_active' => true,
            ],
        ];

        foreach ($prices as $price) {
            ServicePrice::updateOrCreate(
                ['service_type' => $price['service_type']],
                $price
            );
        }

        $this->command->info('Service prices seeded successfully.');
    }
}
