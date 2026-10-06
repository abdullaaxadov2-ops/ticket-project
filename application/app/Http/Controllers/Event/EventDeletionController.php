<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventService;

class EventDeletionController extends Controller
{
    public function __invoke(Event $event, EventService $service)
    {
        $service->delete($event);

        return ['success' => true];
    }
}
