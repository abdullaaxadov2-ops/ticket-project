<?php

namespace App\Listeners;

use App\Events\TicketPurchased;
use App\Mail\NewPurchaseMail;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Mail;

class NotifyOrganiserAboutPurchase implements ShouldQueueAfterCommit
{
    public function handle(TicketPurchased $purchased): void
    {
        Mail::to($purchased->order->event->organizer)->send(new NewPurchaseMail($purchased->order));
    }
}
