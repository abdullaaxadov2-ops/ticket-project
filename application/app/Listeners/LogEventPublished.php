<?php

namespace App\Listeners;

use App\Events\EventPublished;
use Illuminate\Support\Facades\Log;

class LogEventPublished
{
    public function handle(EventPublished $published): void
    {
        Log::info('Мероприятие опубликовано', [
            'event_id' => $published->event->id,
            'organizer_id' => $published->event->organizer_id,
        ]);
    }
}
