<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'EVT-' . Str::upper(Str::random(6)),
            'order_id' => Order::factory(),
            'ticket_type_id' => fn (array $attributes) => TicketType::factory()->create([
                'event_id' => Order::find($attributes['order_id'])->event_id,
            ])->id,
            'event_id' => fn (array $attributes) => Order::find($attributes['order_id'])->event_id,
            'user_id' => fn (array $attributes) => Order::find($attributes['order_id'])->user_id,
            'status' => TicketStatus::Active,
        ];
    }
}
