<?php

namespace App\Http\Controllers\TicketType;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketType\TicketTypeRequest;
use App\Models\Event;
use App\Models\TicketType;
use App\Services\TicketTypeService;

class TicketTypeUpdateController extends Controller
{
    public function __invoke(TicketTypeRequest $request, Event $event, TicketType $ticketType, TicketTypeService $service)
    {
        return [
            'success' => true,
            'data' => $service->update($ticketType, $request->toDTO()),
        ];
    }
}
