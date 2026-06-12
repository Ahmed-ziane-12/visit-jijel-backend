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
            ['name' => 'Ahmed Mansouri', 'email' => 'ahmed@example.com', 'commune' => 'Taher'],
            ['name' => 'Fatima Bensalem', 'email' => 'fatima@example.com', 'commune' => 'El Aouana'],
            ['name' => 'Karim Hadjar', 'email' => 'karim@example.com', 'commune' => 'Kaous'],
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
                    'phone' => '+213 5'.random_int(50, 99).' '.str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT).' '.str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT).' '.str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT),
                    'bio' => 'Business owner passionate about tourism in Jijel.',
                    'wilaya' => 'Jijel',
                    'commune' => $data['commune'],
                ]);
            }
        }

        // 7 clients
        $clients = [
            ['name' => 'Sara Bouzid', 'email' => 'sara@example.com', 'wilaya' => 'Jijel'],
            ['name' => 'Omar Meftah', 'email' => 'omar@example.com', 'wilaya' => 'Skikda'],
            ['name' => 'Lina Guendouzi', 'email' => 'lina@example.com', 'wilaya' => 'Constantine'],
            ['name' => 'Riad Belkacem', 'email' => 'riad@example.com', 'wilaya' => 'Mila'],
            ['name' => 'Nadia Taleb', 'email' => 'nadia@example.com', 'wilaya' => 'Béjaïa'],
            ['name' => 'Yacine Ferhat', 'email' => 'yacine@example.com', 'wilaya' => 'Sétif'],
            ['name' => 'Meriem Sebti', 'email' => 'meriem@example.com', 'wilaya' => 'Jijel'],
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
                    'phone' => '+213 5'.random_int(50, 99).' '.str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT).' '.str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT).' '.str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT),
                    'bio' => 'Travel enthusiast exploring Algeria.',
                    'wilaya' => $data['wilaya'],
                    'commune' => $data['wilaya'],
                ]);
            }
        }
    }
}
