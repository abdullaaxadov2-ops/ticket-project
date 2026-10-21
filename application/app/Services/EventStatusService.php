<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Events\EventStarted;
use App\Models\Event;

class EventStatusService
{
    public function startDueEvents(): int
    {
        $events = Event::where('status', EventStatus::Published)
            ->whereNull('started_at') // started_at — отметка, что событие начала уже было, чтобы не повторять его каждую минуту
            ->where('starts_at', '<=', now())
            ->get();

        foreach ($events as $event) {
            $event->started_at = now();
            $event->save();

            EventStarted::dispatch($event);
        }

        return $events->count();
    }

    public function finishEndedEvents(): int
    {
        return Event::where('status', EventStatus::Published)
            ->where('ends_at', '<=', now())
            ->update(['status' => EventStatus::Finished]);
    }
}
