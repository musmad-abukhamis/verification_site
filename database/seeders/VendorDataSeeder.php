<?php

namespace Database\Seeders;

use App\Models\ActiveVendor;
use App\Models\VendorApi;
use App\Models\VendorNetwork;
use App\Models\VendorPlan;
use Illuminate\Database\Seeder;

class VendorDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create vendor API configuration
        VendorApi::create([
            'vendor1url' => 'https://sandbox.vtpass.com/api/pay',
            'vendor1key' => 'your_vtpass_api_key',
            'vendor2url' => 'https://api.clubkonnect.com/api/pay',
            'vendor2key' => 'your_clubkonnect_api_key',
            'vendor3url' => 'https://api.monnify.com/api/v1/merchant/transactions',
            'vendor3key' => 'your_monnify_api_key',
            'vendor4url' => 'https://api.flutterwave.com/v3/bills',
            'vendor4key' => 'your_flutterwave_api_key',
            'vendor5url' => 'https://api.paystack.co/transaction/initialize',
            'vendor5key' => 'your_paystack_api_key',
        ]);

        // Create vendor network mappings
        VendorNetwork::insert([
            [
                'network' => 'mtn',
                'type' => 'sme',
                'vendor_number' => 1,
                'prefixes' => '0703,0706,0803,0806,0810,0813,0814,0816,0903,0906,0913,0916',
                'is_active' => true,
            ],
            [
                'network' => 'mtn',
                'type' => 'direct',
                'vendor_number' => 1,
                'prefixes' => '0703,0706,0803,0806,0810,0813,0814,0816,0903,0906,0913,0916',
                'is_active' => true,
            ],
            [
                'network' => 'glo',
                'type' => 'direct',
                'vendor_number' => 2,
                'prefixes' => '0705,0805,0807,0811,0815,0905,0915',
                'is_active' => true,
            ],
            [
                'network' => 'airtel',
                'type' => 'direct',
                'vendor_number' => 1,
                'prefixes' => '0701,0708,0802,0808,0812,0901,0902,0904,0907,0912,0911',
                'is_active' => true,
            ],
            [
                'network' => '9mobile',
                'type' => 'direct',
                'vendor_number' => 2,
                'prefixes' => '0809,0817,0818,0908,0909',
                'is_active' => true,
            ],
        ]);

        // Create active vendors (vendor 1 as primary for most networks)
        ActiveVendor::insert([
            ['network' => 'mtn', 'type' => 'sme', 'vendor_number' => 1],
            ['network' => 'mtn', 'type' => 'direct', 'vendor_number' => 1],
            ['network' => 'glo', 'type' => 'direct', 'vendor_number' => 2],
            ['network' => 'airtel', 'type' => 'direct', 'vendor_number' => 1],
            ['network' => '9mobile', 'type' => 'direct', 'vendor_number' => 2],
        ]);

        // Create vendor plan mappings
        VendorPlan::insert([
            // MTN SME Plans
            ['plan_id' => 1, 'vendor_number' => 1, 'vendor_plan_code' => 'mtn-sme-500mb', 'is_active' => true],
            ['plan_id' => 1, 'vendor_number' => 2, 'vendor_plan_code' => 'MTN-SME-500', 'is_active' => true],
            ['plan_id' => 2, 'vendor_number' => 1, 'vendor_plan_code' => 'mtn-sme-1gb', 'is_active' => true],
            ['plan_id' => 2, 'vendor_number' => 2, 'vendor_plan_code' => 'MTN-SME-1GB', 'is_active' => true],
            // Add more mappings as needed
        ]);
    }
}