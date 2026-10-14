<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCheckInTest extends TestCase
{
    use RefreshDatabase;

    private User $organiser;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $this->event = Event::factory()->create(['organizer_id' => $this->organiser->id]);
    }

    // Билет оплаченного заказа на мероприятие организатора из setUp()
    private function createTicket(array $attributes = []): Ticket
    {
        $order = Order::factory()->create(['event_id' => $this->event->id, 'status' => OrderStatus::Paid]);

        return Ticket::factory()->create(['order_id' => $order->id, ...$attributes]);
    }

    private function checkIn(User $user, Ticket $ticket, ?Event $event = null)
    {
        $event ??= $this->event;

        return $this->actingAs($user, 'sanctum')->postJson("/api/events/{$event->id}/tickets/{$ticket->id}/check-in");
    }

    public function testOrganiserCanCheckInTicket(): void
    {
        $ticket = $this->createTicket();

        $response = $this->checkIn($this->organiser, $ticket);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', TicketStatus::Used->value);
        $this->assertNotNull($response->json('data.registration.registered_at'));
        $this->assertSame(TicketStatus::Used, $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_registrations', ['ticket_id' => $ticket->id]);
    }

    public function testCannotCheckInTicketTwice(): void
    {
        $ticket = $this->createTicket();

        $this->checkIn($this->organiser, $ticket)->assertStatus(200);
        $response = $this->checkIn($this->organiser, $ticket);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ticket']);
        $this->assertDatabaseCount('ticket_registrations', 1);
    }

    public function testCannotCheckInTicketOfAnotherEvent(): void
    {
        $otherEvent = Event::factory()->create(['organizer_id' => $this->organiser->id]);
        $ticket = $this->createTicket();

        $response = $this->checkIn($this->organiser, $ticket, $otherEvent);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ticket']);
        $this->assertSame(TicketStatus::Active, $ticket->fresh()->status);
        $this->assertDatabaseCount('ticket_registrations', 0);
    }

    public function testCannotCheckInTicketOfUnpaidOrder(): void
    {
        $order = Order::factory()->create(['event_id' => $this->event->id, 'status' => OrderStatus::Cancelled]);
        $ticket = Ticket::factory()->create(['order_id' => $order->id]);

        $response = $this->checkIn($this->organiser, $ticket);

        $response->assertStatus(422);
        $this->assertDatabaseCount('ticket_registrations', 0);
    }

    public function testCannotCheckInCancelledTicket(): void
    {
        $ticket = $this->createTicket(['status' => TicketStatus::Cancelled]);

        $response = $this->checkIn($this->organiser, $ticket);

        $response->assertStatus(422);
        $this->assertDatabaseCount('ticket_registrations', 0);
    }

    public function testCheckInOfUnknownTicketReturns404(): void
    {
        $response = $this->actingAs($this->organiser, 'sanctum')
            ->postJson("/api/events/{$this->event->id}/tickets/999999/check-in");

        $response->assertStatus(404);
    }

    public function testAdminCanCheckInTicket(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $ticket = $this->createTicket();

        $response = $this->checkIn($admin, $ticket);

        $response->assertStatus(200);
        $this->assertSame(TicketStatus::Used, $ticket->fresh()->status);
    }

    public function testOtherOrganiserCannotCheckIn(): void
    {
        $stranger = User::factory()->create(['role' => UserRole::Organiser]);
        $ticket = $this->createTicket();

        $response = $this->checkIn($stranger, $ticket);

        $response->assertStatus(403);
        $this->assertSame(TicketStatus::Active, $ticket->fresh()->status);
    }

    public function testParticipantCannotCheckInOwnTicket(): void
    {
        $ticket = $this->createTicket();

        $response = $this->checkIn($ticket->user, $ticket);

        $response->assertStatus(403);
        $this->assertSame(TicketStatus::Active, $ticket->fresh()->status);
    }

    public function testGuestCannotCheckIn(): void
    {
        $ticket = $this->createTicket();

        $response = $this->postJson("/api/events/{$this->event->id}/tickets/{$ticket->id}/check-in");

        $response->assertStatus(401);
    }

    public function testOrganiserSeesRegistrationsOfOwnEvent(): void
    {
        $arrived = $this->createTicket();
        $this->createTicket();
        $this->checkIn($this->organiser, $arrived)->assertStatus(200);

        $response = $this->actingAs($this->organiser, 'sanctum')->getJson("/api/events/{$this->event->id}/registrations");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.ticket.number', $arrived->number);
        $response->assertJsonPath('data.0.ticket.user.id', $arrived->user_id);
    }

    public function testOtherOrganiserCannotSeeRegistrations(): void
    {
        $stranger = User::factory()->create(['role' => UserRole::Organiser]);

        $response = $this->actingAs($stranger, 'sanctum')->getJson("/api/events/{$this->event->id}/registrations");

        $response->assertStatus(403);
    }
}
