<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+2 months');

        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+3 hours'),
            'organizer_id' => User::factory(),
            'category_id' => Category::factory(),
            'venue_id' => Venue::factory(),
            'status' => EventStatus::Published,
        ];
    }
}
