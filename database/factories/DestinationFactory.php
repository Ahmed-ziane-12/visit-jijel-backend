<?php

namespace Database\Factories;

use App\Enums\DestinationCategory;
use App\Enums\DestinationState;
use App\Models\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Destination>
 */
class DestinationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city(),
            'description' => fake()->paragraph(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'category' => fake()->randomElement(DestinationCategory::cases()),
            'is_featured' => false,
            'tags' => [],
            'state' => DestinationState::Active,
        ];
    }
}
