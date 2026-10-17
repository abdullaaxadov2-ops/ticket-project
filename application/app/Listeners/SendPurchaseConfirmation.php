<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\PurchaseConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Mail;

class SendPurchaseConfirmation implements ShouldQueueAfterCommit
{
    public function handle(OrderPaid $event): void
    {
        Mail::to($event->order->user)->send(new PurchaseConfirmationMail($event->order));
    }
}
