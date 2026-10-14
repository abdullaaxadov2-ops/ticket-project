<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\TicketRegistrationService;

class EventRegistrationListController extends Controller
{
    public function __invoke(Event $event, TicketRegistrationService $service)
    {
        return $service->getListForEvent($event);
    }
}
