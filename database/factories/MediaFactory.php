<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_type' => Business::class,
            'model_id' => Business::factory(),
            'cloudinary_public_id' => 'jijel/businesses/'.fake()->uuid(),
            'url' => 'https://res.cloudinary.com/test/image/upload/v1/test',
            'secure_url' => 'https://res.cloudinary.com/test/image/upload/v1/test',
            'format' => 'jpg',
            'resource_type' => 'image',
            'collection' => 'gallery',
            'is_cover' => false,
            'sort_order' => 0,
        ];
    }
}
