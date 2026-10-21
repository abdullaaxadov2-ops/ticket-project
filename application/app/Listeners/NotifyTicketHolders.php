<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\EventCancelled;
use App\Mail\EventCancelledMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class NotifyTicketHolders implements ShouldQueue
{
    public function handle(EventCancelled $cancelled): void
    {
        $holders = $cancelled->event->orders()
            ->where('status', OrderStatus::Paid)
            ->with('user')
            ->get()
            ->pluck('user')
            ->unique('id');

        foreach ($holders as $user) {
            Mail::to($user)->send(new EventCancelledMail($cancelled->event));
        }
    }
}
