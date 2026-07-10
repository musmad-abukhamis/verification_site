<?php

namespace Database\Seeders;

use App\Models\DataSetting;
use Illuminate\Database\Seeder;

class DataSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'failover_enabled' => '0',        // global failover switch (off by default)
            'failover_max_attempts' => '0',   // 0 = try every routed vendor
            'reconcile_cutoff_minutes' => '120',
            'requery_interval_minutes' => '5',
        ];

        foreach ($defaults as $key => $value) {
            DataSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        DataSetting::flushCache();
    }
}
