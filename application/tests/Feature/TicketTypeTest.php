<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTypeTest extends TestCase
{
    use RefreshDatabase;

    public function testAnyoneCanListTicketTypesOfEvent(): void
    {
        $event = Event::factory()->create();
        TicketType::factory()->count(2)->create(['event_id' => $event->id]);

        $response = $this->getJson("/api/events/{$event->id}/ticket-types");

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    public function testOwnerOrganiserCanCreateTicketType(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson("/api/events/{$event->id}/ticket-types", [
            'name' => 'Standard',
            'description' => 'Обычный билет',
            'price' => 100000,
            'quantity' => 50,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ticket_types', ['event_id' => $event->id, 'name' => 'Standard']);
    }

    public function testOtherOrganiserCannotCreateTicketTypeForForeignEvent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organiser]);
        $stranger = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $owner->id]);

        $response = $this->actingAs($stranger, 'sanctum')->postJson("/api/events/{$event->id}/ticket-types", [
            'name' => 'VIP',
            'price' => 250000,
            'quantity' => 20,
        ]);

        $response->assertStatus(403);
    }

    public function testParticipantCannotCreateTicketType(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        $event = Event::factory()->create();

        $response = $this->actingAs($participant, 'sanctum')->postJson("/api/events/{$event->id}/ticket-types", [
            'name' => 'VIP',
            'price' => 250000,
            'quantity' => 20,
        ]);

        $response->assertStatus(403);
    }

    public function testCreatingTicketTypeValidatesNegativePrice(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson("/api/events/{$event->id}/ticket-types", [
            'name' => 'Standard',
            'price' => -100,
            'quantity' => 10,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['price']);
    }

    public function testOwnerOrganiserCanUpdateTicketType(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id]);
        $ticketType = TicketType::factory()->create(['event_id' => $event->id, 'quantity' => 50]);

        $response = $this->actingAs($organiser, 'sanctum')->patchJson("/api/events/{$event->id}/ticket-types/{$ticketType->id}", [
            'quantity' => 80,
        ]);

        $response->assertStatus(200);
        $this->assertSame(80, $ticketType->fresh()->quantity);
    }

    public function testOnlyOwnerOrAdminCanDeleteTicketType(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organiser]);
        $stranger = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $owner->id]);
        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);

        $forbidden = $this->actingAs($stranger, 'sanctum')->deleteJson("/api/events/{$event->id}/ticket-types/{$ticketType->id}");
        $forbidden->assertStatus(403);

        $allowed = $this->actingAs($owner, 'sanctum')->deleteJson("/api/events/{$event->id}/ticket-types/{$ticketType->id}");
        $allowed->assertStatus(204);
    }
}
