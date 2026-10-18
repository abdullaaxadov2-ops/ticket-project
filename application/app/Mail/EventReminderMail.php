<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Event $event)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Напоминание: «{$this->event->title}» уже скоро",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.event-reminder',
        );
    }
}
