<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\TicketStatus;
use App\Mail\EventReminderMail;
use App\Models\Event;
use Illuminate\Support\Facades\Mail;

class EventReminderService
{
    public function sendDueReminders(): int
    {
        $events = Event::where('status', EventStatus::Published)
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addDay()])
            ->get();

        foreach ($events as $event) {
            $this->remindTicketHolders($event);

            $event->reminder_sent_at = now();
            $event->save();
        }

        return $events->count();
    }

    private function remindTicketHolders(Event $event): void
    {
        $holders = $event->tickets()
            ->where('status', TicketStatus::Active)
            ->with('user')
            ->get()
            ->pluck('user')
            ->unique('id');

        foreach ($holders as $user) {
            Mail::to($user)->queue(new EventReminderMail($event));
        }
    }
}
