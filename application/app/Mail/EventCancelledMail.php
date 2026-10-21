<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EventCancelledMail extends Mailable
{
    public function __construct(public Event $event)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Мероприятие «{$this->event->title}» отменено",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.event-cancelled',
        );
    }
}
