<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PurchaseConfirmationMail extends Mailable
{
    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Подтверждение покупки билетов',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.purchase-confirmation',
            with: [
                'event' => $this->order->event,
                'tickets' => $this->order->tickets()->with('ticketType')->get(),
            ],
        );
    }
}
