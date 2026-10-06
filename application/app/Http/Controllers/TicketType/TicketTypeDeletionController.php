<?php

namespace App\Http\Controllers\TicketType;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketType;
use App\Services\TicketTypeService;

class TicketTypeDeletionController extends Controller
{
    public function __invoke(Event $event, TicketType $ticketType, TicketTypeService $service)
    {
        $service->delete($ticketType);

        return ['success' => true];
    }
}
