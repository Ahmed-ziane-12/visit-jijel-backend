<?php

namespace Database\Factories;

use App\Enums\PriceUnit;
use App\Models\Business;
use App\Models\BusinessDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessDetail>
 */
class BusinessDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'average_price' => fake()->randomFloat(2, 1000, 50000),
            'price_unit' => PriceUnit::Night,
            'number_of_rooms' => fake()->numberBetween(5, 200),
            'star_rating' => fake()->numberBetween(1, 5),
            'cuisine_type' => fake()->randomElement(['Algerian', 'Mediterranean', 'Italian']),
            'seating_capacity' => fake()->numberBetween(10, 300),
            'amenities' => ['Free Wi-Fi', 'Parking'],
            'services' => ['Room service'],
        ];
    }

    public function empty(): static
    {
        return $this->state(fn () => [
            'average_price' => null,
            'price_unit' => null,
            'number_of_rooms' => null,
            'star_rating' => null,
            'cuisine_type' => null,
            'seating_capacity' => null,
            'amenities' => null,
            'services' => null,
        ]);
    }
}
