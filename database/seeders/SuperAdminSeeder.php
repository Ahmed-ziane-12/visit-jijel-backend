<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'zianihmede@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Change@Me123'),
                'is_admin' => true,
                'is_super_admin' => true,
                'must_reset_password' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
