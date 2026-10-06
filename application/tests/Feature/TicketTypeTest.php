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

    private function ticketTypeData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Standard',
            'description' => 'Обычный билет',
            'price' => 100000,
            'quantity' => 50,
        ], $overrides);
    }

    public function testAnyoneCanListTicketTypesOfEvent(): void
    {
        $event = Event::factory()->create();
        TicketType::factory()->count(2)->create(['event_id' => $event->id]);
        TicketType::factory()->create();

        $response = $this->getJson("/api/events/{$event->id}/ticket-types");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function testOwnerOrganiserCanCreateTicketType(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id]);

        $response = $this->actingAs($organiser, 'sanctum')
            ->postJson("/api/events/{$event->id}/ticket-types", $this->ticketTypeData());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Standard');
        $this->assertDatabaseHas('ticket_types', ['event_id' => $event->id, 'name' => 'Standard']);
    }

    public function testOtherOrganiserCannotCreateTicketTypeForForeignEvent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organiser]);
        $stranger = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $owner->id]);

        $response = $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/events/{$event->id}/ticket-types", $this->ticketTypeData());

        $response->assertStatus(403);
        $this->assertDatabaseCount('ticket_types', 0);
    }

    public function testParticipantCannotCreateTicketType(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        $event = Event::factory()->create();

        $response = $this->actingAs($participant, 'sanctum')
            ->postJson("/api/events/{$event->id}/ticket-types", $this->ticketTypeData());

        $response->assertStatus(403);
    }

    public function testGuestCannotCreateTicketType(): void
    {
        $event = Event::factory()->create();

        $response = $this->postJson("/api/events/{$event->id}/ticket-types", $this->ticketTypeData());

        $response->assertStatus(401);
    }

    public function testCreatingTicketTypeValidatesNegativePrice(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id]);

        $response = $this->actingAs($organiser, 'sanctum')
            ->postJson("/api/events/{$event->id}/ticket-types", $this->ticketTypeData([
            'price' => -100,
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['price']);
    }

    public function testOwnerOrganiserCanUpdateTicketType(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id]);
        $ticketType = TicketType::factory()->create(['event_id' => $event->id, 'quantity' => 50]);

        $response = $this->actingAs($organiser, 'sanctum')
            ->putJson("/api/events/{$event->id}/ticket-types/{$ticketType->id}", $this->ticketTypeData([
            'quantity' => 80,
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('data.quantity', 80);
        $this->assertSame(80, $ticketType->fresh()->quantity);
    }

    public function testAdminCanUpdateTicketType(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $ticketType = TicketType::factory()->create(['quantity' => 50]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/events/{$ticketType->event_id}/ticket-types/{$ticketType->id}", $this->ticketTypeData([
            'quantity' => 80,
        ]));

        $response->assertStatus(200);
        $this->assertSame(80, $ticketType->fresh()->quantity);
    }

    public function testOtherOrganiserCannotUpdateTicketType(): void
    {
        $stranger = User::factory()->create(['role' => UserRole::Organiser]);
        $ticketType = TicketType::factory()->create(['quantity' => 50]);

        $response = $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/events/{$ticketType->event_id}/ticket-types/{$ticketType->id}", $this->ticketTypeData([
            'quantity' => 80,
        ]));

        $response->assertStatus(403);
        $this->assertSame(50, $ticketType->fresh()->quantity);
    }

    public function testUpdatingTicketTypeRequiresAllFields(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id]);
        $ticketType = TicketType::factory()->create(['event_id' => $event->id, 'quantity' => 50]);

        $response = $this->actingAs($organiser, 'sanctum')
            ->putJson("/api/events/{$event->id}/ticket-types/{$ticketType->id}", [
            'quantity' => 80,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'price']);
        $this->assertSame(50, $ticketType->fresh()->quantity);
    }

    public function testTicketTypeOfAnotherEventIsNotFound(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $myEvent = Event::factory()->create(['organizer_id' => $organiser->id]);
        $otherEvent = Event::factory()->create(['organizer_id' => $organiser->id]);
        $ticketType = TicketType::factory()->create(['event_id' => $otherEvent->id]);

        $response = $this->actingAs($organiser, 'sanctum')
            ->deleteJson("/api/events/{$myEvent->id}/ticket-types/{$ticketType->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('ticket_types', ['id' => $ticketType->id]);
    }

    public function testOnlyOwnerOrAdminCanDeleteTicketType(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organiser]);
        $stranger = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $owner->id]);
        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);

        $forbidden = $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/events/{$event->id}/ticket-types/{$ticketType->id}");
        $forbidden->assertStatus(403);

        $allowed = $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/events/{$event->id}/ticket-types/{$ticketType->id}");
        $allowed->assertStatus(200);
        $allowed->assertJsonPath('success', true);
        $this->assertDatabaseMissing('ticket_types', ['id' => $ticketType->id]);
    }
}
