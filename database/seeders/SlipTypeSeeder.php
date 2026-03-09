<?php

namespace Database\Seeders;

use App\Models\SlipType;
use Illuminate\Database\Seeder;

class SlipTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $slipTypes = [
            [
                'code' => 'standard',
                'name' => 'Standard Slip',
                'description' => 'Standard NIN slip with background image',
                'price' => 100.00,
                'component_name' => 'StandardSlip',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'v2',
                'name' => 'Clean Slip',
                'description' => 'Clean design NIN slip without background',
                'price' => 100.00,
                'component_name' => 'StandardSlipV2',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'premium',
                'name' => 'Premium Slip',
                'description' => 'Premium NIN slip with custom background and QR logo',
                'price' => 200.00,
                'component_name' => 'PremiumSlip',
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($slipTypes as $slipType) {
            SlipType::updateOrCreate(
                ['code' => $slipType['code']],
                $slipType
            );
        }

        $this->command->info('Slip types seeded successfully.');
    }
}
