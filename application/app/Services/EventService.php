<?php

namespace App\Services;

use App\Data\Events\EventData;
use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Events\EventCancelled;
use App\Events\EventPublished;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventService
{
    public function create(User $organizer, EventData $data): Event
    {
        $event = new Event((array) $data);
        $event->organizer_id = $organizer->id;
        $event->status = EventStatus::Draft;
        $event->save();

        return $event;
    }

    public function update(Event $event, EventData $data): Event
    {
        $event->fill((array) $data);
        $event->save();

        return $event;
    }

    public function delete(Event $event): void
    {
        $event->delete();
    }

    public function publish(Event $event): Event
    {
        if ($event->status !== EventStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Опубликовать можно только черновик.',
            ]);
        }

        $event->status = EventStatus::Published;
        $event->save();

        EventPublished::dispatch($event);

        return $event;
    }

    public function cancel(Event $event): Event
    {
        if (!in_array($event->status, [EventStatus::Draft, EventStatus::Published], true)) {
            throw ValidationException::withMessages([
                'status' => 'Это мероприятие уже отменено или завершено.',
            ]);
        }

        DB::transaction(function () use ($event) {
            $event->status = EventStatus::Cancelled;
            $event->save();

            // Оплаченные заказы сохраняются, но деньги по ним нужно вернуть
            $event->orders()->where('status', OrderStatus::Paid)->update(['refund_required' => true]);
            // Неоплаченные закрываем, чтобы по ним больше нельзя было заплатить
            $event->orders()->where('status', OrderStatus::Pending)->update(['status' => OrderStatus::Cancelled]);
            $event->tickets()->where('status', TicketStatus::Active)->update(['status' => TicketStatus::Cancelled]);
        });

        EventCancelled::dispatch($event);

        return $event;
    }
}
