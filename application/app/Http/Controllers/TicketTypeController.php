<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketTypeRequest;
use App\Models\Event;
use App\Models\TicketType;

class TicketTypeController extends Controller
{
    public function index(Event $event)
    {
        return $event->ticketTypes;
    }

    public function store(TicketTypeRequest $request, Event $event)
    {
        $this->authorize('create', [TicketType::class, $event]);

        $ticketType = new TicketType($request->validated());
        $ticketType->event_id = $event->id;
        $ticketType->save();

        return response()->json($ticketType, 201);
    }

    public function update(TicketTypeRequest $request, Event $event, TicketType $ticketType)
    {
        $this->authorize('update', $ticketType);

        $ticketType->update($request->validated());

        return $ticketType;
    }

    public function destroy(Event $event, TicketType $ticketType)
    {
        $this->authorize('delete', $ticketType);

        $ticketType->delete();

        return response()->noContent();
    }
}
