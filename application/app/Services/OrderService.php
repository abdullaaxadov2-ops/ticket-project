<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function create(User $user, Event $event, array $items): Order
    {
        if ($event->status !== EventStatus::Published) {
            throw ValidationException::withMessages([
                'event' => 'Мероприятие недоступно для покупки.',
            ]);
        }

        return DB::transaction(function () use ($user, $event, $items) {
            $ticketTypes = TicketType::whereIn('id', array_column($items, 'ticket_type_id'))
                ->where('event_id', $event->id)
                ->lockForUpdate()
                // не дает случится race condition. Второй запрос будет ждать, пока первый не завершится
                ->get()
                ->keyBy('id');

            $total = 0;

            foreach ($items as $item) {
                $ticketType = $ticketTypes->get($item['ticket_type_id']);

                if ($ticketType === null) {
                    throw ValidationException::withMessages([
                        'items' => 'Тип билета не относится к этому мероприятию.',
                    ]);
                }

                if ($ticketType->availableCount() < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => "Недостаточно билетов типа «{$ticketType->name}».",
                    ]);
                }

                $total += $ticketType->price * $item['quantity'];
            }

            $order = new Order();
            $order->user_id = $user->id;
            $order->event_id = $event->id;
            $order->total_amount = $total;
            $order->status = OrderStatus::Pending;
            $order->save();

            foreach ($items as $item) {
                $ticketType = $ticketTypes->get($item['ticket_type_id']);

                $order->items()->create([
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $item['quantity'],
                    'price' => $ticketType->price,
                ]);

                $ticketType->increment('sold_count', $item['quantity']);
                // создаём строки заказа с копией текущей цены и резервируем билеты
            }

            return $order->load('items');
            // все данные в items в фотмате жсон
        });
    }
}
