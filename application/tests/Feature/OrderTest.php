<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function testParticipantCanCreateOrder(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        $event = Event::factory()->create(['status' => EventStatus::Published]);
        $standard = TicketType::factory()->create(['event_id' => $event->id, 'price' => 100000, 'quantity' => 50]);
        $vip = TicketType::factory()->create(['event_id' => $event->id, 'price' => 250000, 'quantity' => 10]);

        $response = $this->actingAs($participant, 'sanctum')->postJson("/api/events/{$event->id}/orders", [
            'items' => [
                ['ticket_type_id' => $standard->id, 'quantity' => 2],
                ['ticket_type_id' => $vip->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('total_amount', '450000.00');
        $response->assertJsonPath('status', OrderStatus::Pending->value);
        $response->assertJsonCount(2, 'items');
        $this->assertSame(2, $standard->fresh()->sold_count);
        $this->assertSame(1, $vip->fresh()->sold_count);
    }

    public function testCannotOrderUnpublishedEvent(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        $event = Event::factory()->create(['status' => EventStatus::Draft]);
        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAs($participant, 'sanctum')->postJson("/api/events/{$event->id}/orders", [
            'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function testCannotOrderMoreTicketsThanAvailable(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        $event = Event::factory()->create(['status' => EventStatus::Published]);
        $ticketType = TicketType::factory()->create(['event_id' => $event->id, 'quantity' => 5]);
        $ticketType->sold_count = 4;
        $ticketType->save();

        $response = $this->actingAs($participant, 'sanctum')->postJson("/api/events/{$event->id}/orders", [
            'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 2]],
        ]);

        $response->assertStatus(422);
        $this->assertSame(4, $ticketType->fresh()->sold_count);
        $this->assertDatabaseCount('orders', 0);
    }

    public function testCannotOrderTicketTypeOfAnotherEvent(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        $event = Event::factory()->create(['status' => EventStatus::Published]);
        $foreignTicketType = TicketType::factory()->create();

        $response = $this->actingAs($participant, 'sanctum')->postJson("/api/events/{$event->id}/orders", [
            'items' => [['ticket_type_id' => $foreignTicketType->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422);
    }

    public function testOrganiserCannotCreateOrder(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['status' => EventStatus::Published]);
        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson("/api/events/{$event->id}/orders", [
            'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(403);
    }

    public function testGuestCannotCreateOrder(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::Published]);

        $response = $this->postJson("/api/events/{$event->id}/orders", ['items' => []]);

        $response->assertStatus(401);
    }

    public function testParticipantSeesOnlyOwnOrders(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        Order::factory()->count(2)->create(['user_id' => $participant->id]);
        Order::factory()->create();

        $response = $this->actingAs($participant, 'sanctum')->getJson('/api/orders');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function testOrganiserSeesOnlyOrdersOfOwnEvents(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $ownEvent = Event::factory()->create(['organizer_id' => $organiser->id]);
        Order::factory()->count(3)->create(['event_id' => $ownEvent->id]);
        Order::factory()->create();

        $response = $this->actingAs($organiser, 'sanctum')->getJson('/api/orders');

        $response->assertJsonCount(3, 'data');
    }

    public function testParticipantCannotViewForeignOrder(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        $foreignOrder = Order::factory()->create();

        $response = $this->actingAs($participant, 'sanctum')->getJson("/api/orders/{$foreignOrder->id}");

        $response->assertStatus(403);
    }
}
