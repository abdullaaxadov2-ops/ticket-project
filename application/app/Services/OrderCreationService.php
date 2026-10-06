<?php

namespace App\Services;

use App\Data\Orders\CreateOrderData;
use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCreationService
{
    public function create(User $user, Event $event, CreateOrderData $data): Order
    {
        if ($event->status !== EventStatus::Published) {
            throw ValidationException::withMessages([
                'event' => 'Мероприятие недоступно для покупки.',
            ]);
        }

        return DB::transaction(function () use ($user, $event, $data) {
            $ticketTypes = TicketType::whereIn('id', array_map(fn ($item) => $item->ticket_type_id, $data->items))
                ->where('event_id', $event->id)
                ->lockForUpdate()
                // не дает случится race condition. Второй запрос будет ждать, пока первый не завершится
                ->get()
                ->keyBy('id');

            $total = 0;

            foreach ($data->items as $item) {
                $ticketType = $ticketTypes->get($item->ticket_type_id);

                if ($ticketType === null) {
                    throw ValidationException::withMessages([
                        'items' => 'Тип билета не относится к этому мероприятию.',
                    ]);
                }

                if ($ticketType->availableCount() < $item->quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Недостаточно билетов типа «{$ticketType->name}».",
                    ]);
                }

                $total += $ticketType->price * $item->quantity;
            }

            $order = new Order();
            $order->user_id = $user->id;
            $order->event_id = $event->id;
            $order->total_amount = $total;
            $order->status = OrderStatus::Pending;
            $order->save();

            foreach ($data->items as $item) {
                $ticketType = $ticketTypes->get($item->ticket_type_id);

                $order->items()->create([
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $item->quantity,
                    'price' => $ticketType->price,
                ]);

                $ticketType->increment('sold_count', $item->quantity);
                // создаём строки заказа с копией текущей цены и резервируем билеты
            }

            return $order->load('items');
            // подгружаем строки заказа, чтобы они попали в жсон ответ
        });
    }
}
