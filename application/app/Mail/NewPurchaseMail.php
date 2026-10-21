<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewPurchaseMail extends Mailable
{
    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Новая покупка: «{$this->order->event->title}»",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.new-purchase',
            with: [
                'event' => $this->order->event,
                'ticketCount' => $this->order->items->sum('quantity'),
            ],
        );
    }
}
