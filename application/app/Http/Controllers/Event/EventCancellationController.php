<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventService;

class EventCancellationController extends Controller
{
    public function __invoke(Event $event, EventService $service)
    {
        return [
            'success' => true,
            'data' => $service->cancel($event),
        ];
    }
}
