<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SendsPaymentWebhooks;

class TicketTest extends TestCase
{
    use RefreshDatabase, SendsPaymentWebhooks;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payment.secret' => 'test-secret']);
    }

    // Заказ, ожидающий оплаты: Standard × 2 + VIP × 1
    private function createPendingOrder(): Order
    {
        $order = Order::factory()->create(['total_amount' => 450000, 'payment_reference' => 'ref-1']);
        $standard = TicketType::factory()->create(['event_id' => $order->event_id, 'price' => 100000, 'sold_count' => 2]);
        $vip = TicketType::factory()->create(['event_id' => $order->event_id, 'price' => 250000, 'sold_count' => 1]);
        $order->items()->create(['ticket_type_id' => $standard->id, 'quantity' => 2, 'price' => 100000]);
        $order->items()->create(['ticket_type_id' => $vip->id, 'quantity' => 1, 'price' => 250000]);

        return $order;
    }

    public function testPaidWebhookIssuesTicketForEachSeat(): void
    {
        $order = $this->createPendingOrder();

        $this->sendPaymentWebhook(['order_id' => 'ref-1', 'amount' => 45000000, 'status' => 1])
            ->assertStatus(200);

        $tickets = Ticket::where('order_id', $order->id)->get();
        $this->assertCount(3, $tickets);

        foreach ($tickets as $ticket) {
            $this->assertSame($order->user_id, $ticket->user_id);
            $this->assertSame($order->event_id, $ticket->event_id);
            $this->assertSame(TicketStatus::Active, $ticket->status);
            $this->assertMatchesRegularExpression('/^EVT-[A-Z0-9]{6}$/', $ticket->number);
        }

        $this->assertCount(3, $tickets->pluck('number')->unique());
        $this->assertSame(2, $tickets->where('ticket_type_id', $order->items[0]->ticket_type_id)->count());
        $this->assertSame(1, $tickets->where('ticket_type_id', $order->items[1]->ticket_type_id)->count());
    }

    public function testFailedWebhookDoesNotIssueTickets(): void
    {
        $this->createPendingOrder();

        $this->sendPaymentWebhook(['order_id' => 'ref-1', 'amount' => 45000000, 'status' => 2])
            ->assertStatus(200);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function testRepeatedPaidWebhookDoesNotDuplicateTickets(): void
    {
        $this->createPendingOrder();
        $payload = ['order_id' => 'ref-1', 'amount' => 45000000, 'status' => 1];

        $this->sendPaymentWebhook($payload)->assertStatus(200);
        $this->sendPaymentWebhook($payload)->assertStatus(200);

        $this->assertDatabaseCount('tickets', 3);
    }

    public function testParticipantSeesOnlyOwnTickets(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        Ticket::factory()->count(2)->create(['user_id' => $participant->id]);
        Ticket::factory()->create();

        $response = $this->actingAs($participant, 'sanctum')->getJson('/api/tickets');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure(['data' => [['number', 'status', 'event', 'ticket_type']]]);
    }

    public function testOrganiserSeesTicketsOfOwnEvents(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id]);
        $order = Order::factory()->create(['event_id' => $event->id]);
        $ticket = Ticket::factory()->create(['order_id' => $order->id]);
        Ticket::factory()->create();

        $response = $this->actingAs($organiser, 'sanctum')->getJson('/api/tickets');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ticket->id);
    }

    public function testAdminSeesAllTickets(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Ticket::factory()->count(3)->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/tickets');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function testGuestCannotListTickets(): void
    {
        $response = $this->getJson('/api/tickets');

        $response->assertStatus(401);
    }

    public function testOwnerCanViewTicket(): void
    {
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($ticket->user, 'sanctum')->getJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.number', $ticket->number);
        $response->assertJsonPath('data.event.id', $ticket->event_id);
    }

    public function testOrganiserCanViewTicketOfOwnEvent(): void
    {
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($ticket->event->organizer, 'sanctum')->getJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(200);
    }

    public function testStrangerCannotViewTicket(): void
    {
        $stranger = User::factory()->create(['role' => UserRole::Participant]);
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($stranger, 'sanctum')->getJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(403);
    }
}
