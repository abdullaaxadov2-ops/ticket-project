<?php

namespace App\Services;

use App\Data\TicketTypes\TicketTypeData;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Collection;

class TicketTypeService
{
    public function getList(Event $event): Collection
    {
        return $event->ticketTypes;
    }

    public function create(Event $event, TicketTypeData $data): TicketType
    {
        $ticketType = new TicketType((array) $data);
        $ticketType->event_id = $event->id;
        $ticketType->save();

        return $ticketType;
    }

    public function update(TicketType $ticketType, TicketTypeData $data): TicketType
    {
        $ticketType->fill((array) $data);
        $ticketType->save();

        return $ticketType;
    }

    public function delete(TicketType $ticketType): void
    {
        $ticketType->delete();
    }
}
