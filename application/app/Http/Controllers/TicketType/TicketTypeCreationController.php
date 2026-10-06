<?php

namespace App\Http\Controllers\TicketType;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketType\TicketTypeRequest;
use App\Models\Event;
use App\Services\TicketTypeService;

class TicketTypeCreationController extends Controller
{
    public function __invoke(TicketTypeRequest $request, Event $event, TicketTypeService $service)
    {
        $ticketType = $service->create($event, $request->toDTO());

        return response()->json([
            'success' => true,
            'data' => $ticketType,
        ], 201);
    }
}
