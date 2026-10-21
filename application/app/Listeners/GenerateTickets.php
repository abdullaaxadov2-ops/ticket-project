<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Events\TicketPurchased;
use App\Services\TicketService;

class GenerateTickets
{
    public function __construct(private TicketService $ticketService)
    {
    }

    public function handle(OrderPaid $event): void
    {
        $this->ticketService->issueForOrder($event->order);

        TicketPurchased::dispatch($event->order);
    }
}
