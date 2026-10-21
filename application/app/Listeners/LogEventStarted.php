<?php

namespace App\Listeners;

use App\Events\EventStarted;
use Illuminate\Support\Facades\Log;

class LogEventStarted
{
    public function handle(EventStarted $started): void
    {
        Log::info('Мероприятие началось', [
            'event_id' => $started->event->id,
        ]);
    }
}
