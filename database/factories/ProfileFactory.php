<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => 'client',
            'phone' => fake()->phoneNumber(),
            'bio' => fake()->sentence(),
            'wilaya' => 'Jijel',
            'commune' => fake()->city(),
        ];
    }

    public function client(): static
    {
        return $this->state(fn () => ['role' => 'client']);
    }

    public function businessOwner(): static
    {
        return $this->state(fn () => ['role' => 'business_owner']);
    }
}
