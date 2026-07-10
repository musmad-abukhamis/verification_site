<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'phone' => '08000000000',
            'role' => UserRole::ADMIN,
        ]);

        $this->call([
            VendorSeeder::class,
            NetworkPrefixSeeder::class,
            PlanSeeder::class,
            DataRoutingSeeder::class,
            DataSettingSeeder::class,
            VerifyApiConfigSeeder::class,
        ]);
    }
}
