<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\EventRequest;
use App\Models\Event;
use App\Services\EventService;

class EventUpdateController extends Controller
{
    public function __invoke(EventRequest $request, Event $event, EventService $service)
    {
        return [
            'success' => true,
            'data' => $service->update($event, $request->toDTO()),
        ];
    }
}
