<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class TicketService
{
    public function issueForOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                $ticket = new Ticket();
                $ticket->number = $this->generateNumber();
                $ticket->order_id = $order->id;
                $ticket->ticket_type_id = $item->ticket_type_id;
                $ticket->event_id = $order->event_id;
                $ticket->user_id = $order->user_id;
                $ticket->status = TicketStatus::Active;
                $ticket->save();
            }
        }
    }

    public function getList(User $user): LengthAwarePaginator
    {
        $query = Ticket::with(['event', 'ticketType'])->latest();

        if ($user->isOrganiser()) {
            $query->whereHas('event', fn ($q) => $q->where('organizer_id', $user->id));
        } elseif (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        return $query->paginate(15);
    }

    private function generateNumber(): string
    {
        do {
            $number = 'EVT-' . Str::upper(Str::random(6));
        } while (Ticket::where('number', $number)->exists());

        return $number;
    }
}
