<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'total_amount' => fake()->randomFloat(2, 50000, 500000),
            'status' => OrderStatus::Pending,
        ];
    }
}
