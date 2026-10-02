<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TicketType>
 */
class TicketTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['Standard', 'VIP', 'Early Bird']),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 50000, 300000),
            'quantity' => fake()->numberBetween(20, 100),
        ];
    }
}
