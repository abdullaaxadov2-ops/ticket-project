<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin()
            || $ticket->user_id === $user->id
            || $ticket->event->organizer_id === $user->id;
    }
}
