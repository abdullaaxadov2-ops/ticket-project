<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;

class TicketTypePolicy
{
    public function create(User $user, Event $event): bool
    {
        return $user->isOrganiser() && $event->organizer_id === $user->id;
    }

    public function update(User $user, TicketType $ticketType): bool
    {
        return $user->isAdmin() || $ticketType->event->organizer_id === $user->id;
    }

    public function delete(User $user, TicketType $ticketType): bool
    {
        return $user->isAdmin() || $ticketType->event->organizer_id === $user->id;
    }
}
