<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketRegistration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketRegistrationService
{
    public function checkIn(Event $event, Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($event, $ticket) {
            $ticket = Ticket::lockForUpdate()->findOrFail($ticket->id);

            if ($ticket->event_id !== $event->id) {
                throw ValidationException::withMessages([
                    'ticket' => 'Билет выпущен на другое мероприятие.',
                ]);
            }

            if ($ticket->order->status !== OrderStatus::Paid) {
                throw ValidationException::withMessages([
                    'ticket' => 'Заказ по этому билету не оплачен.',
                ]);
            }

            if ($ticket->status !== TicketStatus::Active) {
                throw ValidationException::withMessages([
                    'ticket' => $ticket->status === TicketStatus::Used
                        ? 'Билет уже использован.'
                        : 'Билет отменён.',
                ]);
            }

            $registration = new TicketRegistration();
            $registration->ticket_id = $ticket->id;
            $registration->registered_at = now();
            $registration->save();

            $ticket->status = TicketStatus::Used;
            $ticket->save();

            return $ticket->load('registration');
        });
    }

    public function getListForEvent(Event $event): LengthAwarePaginator
    {
        return TicketRegistration::with(['ticket.user', 'ticket.ticketType'])
            ->whereHas('ticket', fn ($q) => $q->where('event_id', $event->id))
            ->latest('registered_at')
            ->paginate(15);
    }
}
