<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Mail\EventReminderMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function createEvent(array $attributes = []): Event
    {
        return Event::factory()->create([
            'starts_at' => now()->addHours(10),
            'ends_at' => now()->addHours(13),
            ...$attributes,
        ]);
    }

    // Билет оплаченного заказа на указанное мероприятие
    private function createTicket(Event $event, array $attributes = []): Ticket
    {
        $order = Order::factory()->create(['event_id' => $event->id, 'status' => OrderStatus::Paid]);

        return Ticket::factory()->create(['order_id' => $order->id, ...$attributes]);
    }

    public function testReminderIsQueuedForTicketHoldersOfEventStartingWithin24Hours(): void
    {
        $event = $this->createEvent();
        $first = $this->createTicket($event);
        $second = $this->createTicket($event);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Mail::assertQueued(EventReminderMail::class, 2);
        Mail::assertQueued(EventReminderMail::class, fn (EventReminderMail $mail) => $mail->hasTo($first->user->email));
        Mail::assertQueued(EventReminderMail::class, fn (EventReminderMail $mail) => $mail->hasTo($second->user->email));
        $this->assertNotNull($event->fresh()->reminder_sent_at);
    }

    public function testHolderOfSeveralTicketsGetsOneReminder(): void
    {
        $event = $this->createEvent();
        $order = Order::factory()->create(['event_id' => $event->id, 'status' => OrderStatus::Paid]);
        Ticket::factory()->count(3)->create(['order_id' => $order->id]);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Mail::assertQueued(EventReminderMail::class, 1);
    }

    public function testCancelledTicketHolderGetsNoReminder(): void
    {
        $event = $this->createEvent();
        $this->createTicket($event, ['status' => TicketStatus::Cancelled]);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function testReminderIsNotSentTwice(): void
    {
        $event = $this->createEvent();
        $this->createTicket($event);

        $this->artisan('events:send-reminders')->assertSuccessful();
        $this->artisan('events:send-reminders')->assertSuccessful();

        Mail::assertQueued(EventReminderMail::class, 1);
    }

    public function testNoReminderForEventStartingLaterThan24Hours(): void
    {
        $event = $this->createEvent(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHours(3)]);
        $this->createTicket($event);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertNull($event->fresh()->reminder_sent_at);
    }

    public function testNoReminderForEventThatAlreadyStarted(): void
    {
        $event = $this->createEvent(['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(2)]);
        $this->createTicket($event);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function testNoReminderForCancelledEvent(): void
    {
        $event = $this->createEvent(['status' => EventStatus::Cancelled]);
        $this->createTicket($event);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function testReminderCommandIsScheduledHourly(): void
    {
        // Расписание из bootstrap/app.php регистрируется при запуске Artisan
        $this->artisan('schedule:list')->assertSuccessful();

        /** @var \Illuminate\Console\Scheduling\Event|null $scheduled */  // подсказка типа expression
        $scheduled = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'events:send-reminders'));

        $this->assertNotNull($scheduled);
        $this->assertSame('0 * * * *', $scheduled->expression);
    }

    public function testReminderEmailContent(): void
    {
        $event = $this->createEvent(['title' => 'PHP Conference 2026']);

        $mail = new EventReminderMail($event);

        $mail->assertSeeInHtml('PHP Conference 2026');
        $mail->assertSeeInHtml($event->venue->name);
        $mail->assertSeeInHtml($event->starts_at->format('d.m.Y H:i'));
    }
}
