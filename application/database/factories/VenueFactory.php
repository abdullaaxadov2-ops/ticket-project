<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Venue>
 */
class VenueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'address' => fake()->address(),
            'description' => fake()->sentence(),
            'capacity' => fake()->numberBetween(50, 1000),
        ];
    }
}
