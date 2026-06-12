<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password123');

        // 3 business owners
        $owners = [
            ['name' => 'Ahmed Mansouri', 'email' => 'ahmed@example.com'],
            ['name' => 'Fatima Bensalem', 'email' => 'fatima@example.com'],
            ['name' => 'Karim Hadjar', 'email' => 'karim@example.com'],
        ];

        foreach ($owners as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $password,
                    'email_verified_at' => now(),
                ]
            );

            if ($user->wasRecentlyCreated) {
                $user->profile()->create([
                    'role' => 'business_owner',
                    'phone' => fake()->phoneNumber(),
                    'bio' => fake()->paragraph(),
                    'wilaya' => 'Jijel',
                    'commune' => fake()->randomElement([
                        'Jijel Centre', 'El Aouana', 'Chekfa', 'Taher',
                        'Djimla', 'Settara', 'El Milia', 'Kaous',
                    ]),
                ]);
            }
        }

        // 7 clients
        $clients = [
            ['name' => 'Sara Bouzid', 'email' => 'sara@example.com'],
            ['name' => 'Omar Meftah', 'email' => 'omar@example.com'],
            ['name' => 'Lina Guendouzi', 'email' => 'lina@example.com'],
            ['name' => 'Riad Belkacem', 'email' => 'riad@example.com'],
            ['name' => 'Nadia Taleb', 'email' => 'nadia@example.com'],
            ['name' => 'Yacine Ferhat', 'email' => 'yacine@example.com'],
            ['name' => 'Meriem Sebti', 'email' => 'meriem@example.com'],
        ];

        foreach ($clients as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $password,
                    'email_verified_at' => now(),
                ]
            );

            if ($user->wasRecentlyCreated) {
                $user->profile()->create([
                    'role' => 'client',
                    'phone' => fake()->phoneNumber(),
                    'bio' => fake()->sentence(),
                    'wilaya' => fake()->randomElement([
                        'Jijel', 'Skikda', 'Mila', 'Constantine',
                        'Béjaïa', 'Sétif', 'Bordj Bou Arréridj',
                    ]),
                    'commune' => fake()->city(),
                ]);
            }
        }
    }
}
