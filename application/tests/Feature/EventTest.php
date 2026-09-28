<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(): array
    {
        return [
            'title' => 'Мероприятие от Алифа',
            'description' => 'Алиф 365',
            'starts_at' => now()->addDays(10)->toDateTimeString(),
            'ends_at' => now()->addDays(11)->toDateTimeString(),
            'category_id' => Category::factory()->create()->id,
            'venue_id' => Venue::factory()->create()->id,
        ];
    }

    public function testGuestSeesOnlyPublishedEvents(): void
    {
        Event::factory()->create(['status' => EventStatus::Published]);
        Event::factory()->create(['status' => EventStatus::Draft]);

        $response = $this->getJson('/api/events');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function testOrganiserCanCreateEvent(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson('/api/events', $this->validPayload());

        $response->assertStatus(201);
        $this->assertDatabaseHas('events', [
            'title' => 'Мероприятие от Алифа',
            'organizer_id' => $organiser->id,
            'status' => EventStatus::Draft->value,
        ]);
    }

    public function testParticipantCannotCreateEvent(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);

        $response = $this->actingAs($participant, 'sanctum')->postJson('/api/events', $this->validPayload());

        $response->assertStatus(403);
    }

    public function testEndDateMustBeAfterStartDate(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $payload = $this->validPayload();
        $payload['ends_at'] = now()->addDays(5)->toDateTimeString();
        $payload['starts_at'] = now()->addDays(10)->toDateTimeString();

        $response = $this->actingAs($organiser, 'sanctum')->postJson('/api/events', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ends_at']);
    }

    public function testOrganiserCanUpdateOwnEvent(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id, 'title' => 'Старое название']);

        $response = $this->actingAs($organiser, 'sanctum')->patchJson("/api/events/{$event->id}", [
            'title' => 'Новое название',
        ]);

        $response->assertStatus(200);
        $this->assertSame('Новое название', $event->fresh()->title);
    }

    public function testOrganiserCannotUpdateOthersEvent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organiser]);
        $otherOrganiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $owner->id]);

        $response = $this->actingAs($otherOrganiser, 'sanctum')->patchJson("/api/events/{$event->id}", [
            'title' => 'Чужое название',
        ]);

        $response->assertStatus(403);
    }

    public function testAdminCanUpdateAnyEvent(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/events/{$event->id}", [
            'title' => 'Изменено админом',
        ]);

        $response->assertStatus(200);
    }

    public function testOrganiserCannotDeleteEvent(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id]);

        $response = $this->actingAs($organiser, 'sanctum')->deleteJson("/api/events/{$event->id}");

        $response->assertStatus(403);
    }

    public function testAdminCanDeleteEvent(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/events/{$event->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }
}
