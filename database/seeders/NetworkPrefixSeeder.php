<?php

namespace Database\Seeders;

use App\Models\NetworkPrefix;
use Illuminate\Database\Seeder;

/**
 * Current known NCC prefix sets. Networks are stored lowercase to match the
 * buy-data page's network tab values. Admin can add new prefixes without a
 * redeploy (Nigeria introduces new ones regularly).
 */
class NetworkPrefixSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'mtn' => ['0703', '0706', '0803', '0806', '0810', '0813', '0814', '0816', '0903', '0906', '0913', '0916', '07025', '07026', '0704', '0707'],
            'airtel' => ['0701', '0708', '0802', '0808', '0812', '0901', '0902', '0904', '0907', '0912'],
            'glo' => ['0705', '0805', '0807', '0811', '0815', '0905', '0915'],
            '9mobile' => ['0809', '0817', '0818', '0908', '0909'],
        ];

        foreach ($map as $network => $prefixes) {
            foreach ($prefixes as $prefix) {
                NetworkPrefix::updateOrCreate(
                    ['network' => $network, 'prefix' => $prefix],
                    [],
                );
            }
        }
    }
}
