<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\TicketRegistrationService;

class TicketCheckInController extends Controller
{
    public function __invoke(Event $event, Ticket $ticket, TicketRegistrationService $service)
    {
        return [
            'success' => true,
            'data' => $service->checkIn($event, $ticket),
        ];
    }
}
