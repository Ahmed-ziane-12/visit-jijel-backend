<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            DestinationSeeder::class,
            PlanSeeder::class,
        ]);

        // Demo accounts are for local/dev environments only. They must never
        // be created on a production deployment.
        if (! app()->environment('production')) {
            $this->call(UserSeeder::class);
        }
    }
}
