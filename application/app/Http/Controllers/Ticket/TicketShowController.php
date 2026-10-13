<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Ticket;

class TicketShowController extends Controller
{
    public function __invoke(Ticket $ticket)
    {
        return [
            'data' => $ticket->load(['event', 'ticketType']),
        ];
    }
}
