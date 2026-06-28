<?php

namespace Database\Seeders;

use App\Models\Network;
use Illuminate\Database\Seeder;

/**
 * Per-vendor network codes (ported from the production `abcweb` DB).
 *
 * id is stored lowercase because DataPurchaseController resolves it via
 * Network::find($formNetwork) where the form value is lowercase (mtn/glo/...).
 * Only vendor1network is meaningful (bozavtu); the rest mirror production '1'.
 */
class NetworkSeeder extends Seeder
{
    public function run(): void
    {
        $networks = [
            ['id' => 'mtn',     'vendor1network' => '1'],
            ['id' => 'airtel',  'vendor1network' => '2'],
            ['id' => 'glo',     'vendor1network' => '3'],
            ['id' => '9mobile', 'vendor1network' => '4'],
        ];

        foreach ($networks as $network) {
            Network::updateOrCreate(['id' => $network['id']], array_merge($network, [
                'vendor2network' => '1',
                'vendor3network' => '1',
                'vendor4network' => '1',
                'vendor5network' => '1',
            ]));
        }
    }
}
