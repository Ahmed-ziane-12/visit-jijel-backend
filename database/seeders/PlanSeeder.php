<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'description' => 'Get started with basic features at no cost.',
                'price' => 0,
                'currency' => 'DZD',
                'max_businesses' => 1,
                'max_listings_per_business' => 1,
                'featured_listing' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Standard',
                'description' => 'Grow your presence with more businesses and listings.',
                'price' => 1490,
                'currency' => 'DZD',
                'max_businesses' => 2,
                'max_listings_per_business' => 10,
                'featured_listing' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Premium',
                'description' => 'Unlock unlimited listings and maximum exposure.',
                'price' => 3990,
                'currency' => 'DZD',
                'max_businesses' => 10,
                'max_listings_per_business' => -1,
                'featured_listing' => true,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate($plan);
        }
    }
}
