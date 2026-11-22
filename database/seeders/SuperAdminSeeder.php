<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('seeding.super_admin.email');
        $password = config('seeding.super_admin.password');

        if (! $email || ! $password) {
            if (app()->environment('production')) {
                $this->command?->warn(
                    'SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD are not set; skipping super admin creation.'
                );

                return;
            }

            $email = 'super-admin@visitjijel.test';
            $password = Str::password(16);

            $this->command?->warn(
                "Generated super admin credentials (store them now): {$email} / {$password}"
            );
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
            ]
        );

        if (! $user->wasRecentlyCreated) {
            return;
        }

        // Privilege flags are not mass assignable on purpose.
        $user->forceFill([
            'is_admin' => true,
            'is_super_admin' => true,
            'must_reset_password' => true,
            'email_verified_at' => now(),
        ])->save();
    }
}
