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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Мероприятие от Алифа',
            'description' => 'Алиф 365',
            'starts_at' => now()->addDays(10)->toDateTimeString(),
            'ends_at' => now()->addDays(11)->toDateTimeString(),
            'category_id' => Category::factory()->create()->id,
            'venue_id' => Venue::factory()->create()->id,
        ], $overrides);
    }

    public function testGuestSeesOnlyPublishedEvents(): void
    {
        Event::factory()->create(['status' => EventStatus::Published]);
        Event::factory()->create(['status' => EventStatus::Draft]);

        $response = $this->getJson('/api/events');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function testAnyoneCanSeePublishedEvent(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::Published, 'title' => 'Открытое']);

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Открытое');
    }

    public function testGuestCannotSeeDraftEvent(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::Draft]);

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertStatus(404);
    }

    public function testOtherOrganiserCannotSeeDraftEvent(): void
    {
        $stranger = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['status' => EventStatus::Draft]);

        $response = $this->actingAs($stranger, 'sanctum')->getJson("/api/events/{$event->id}");

        $response->assertStatus(404);
    }

    public function testOwnerCanSeeOwnDraftEvent(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['status' => EventStatus::Draft, 'organizer_id' => $organiser->id]);

        $response = $this->actingAs($organiser, 'sanctum')->getJson("/api/events/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $event->id);
    }

    public function testOrganiserCanCreateEvent(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson('/api/events', $this->validPayload());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.title', 'Мероприятие от Алифа');
        $this->assertDatabaseHas('events', [
            'title' => 'Мероприятие от Алифа',
            'organizer_id' => $organiser->id,
            'status' => EventStatus::Draft->value,
        ]);
    }

    public function testClientCannotSetStatusOrOrganizerOnCreate(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $other = User::factory()->create(['role' => UserRole::Organiser]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson('/api/events', $this->validPayload([
            'status' => EventStatus::Published->value,
            'organizer_id' => $other->id,
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('events', [
            'organizer_id' => $organiser->id,
            'status' => EventStatus::Draft->value,
        ]);
    }

    public function testParticipantCannotCreateEvent(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);

        $response = $this->actingAs($participant, 'sanctum')->postJson('/api/events', $this->validPayload());

        $response->assertStatus(403);
        $this->assertDatabaseCount('events', 0);
    }

    public function testGuestCannotCreateEvent(): void
    {
        $response = $this->postJson('/api/events', $this->validPayload());

        $response->assertStatus(401);
    }

    public function testEndDateMustBeAfterStartDate(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson('/api/events', $this->validPayload([
            'starts_at' => now()->addDays(10)->toDateTimeString(),
            'ends_at' => now()->addDays(5)->toDateTimeString(),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ends_at']);
    }

    public function testOrganiserCanUpdateOwnEvent(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id, 'title' => 'Старое название']);

        $response = $this->actingAs($organiser, 'sanctum')->putJson("/api/events/{$event->id}", $this->validPayload([
            'title' => 'Новое название',
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Новое название');
        $this->assertSame('Новое название', $event->fresh()->title);
    }

    public function testUpdatingEventRequiresAllFields(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id, 'title' => 'Старое название']);

        $response = $this->actingAs($organiser, 'sanctum')->putJson("/api/events/{$event->id}", [
            'title' => 'Новое название',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['starts_at', 'ends_at', 'category_id', 'venue_id']);
        $this->assertSame('Старое название', $event->fresh()->title);
    }

    public function testOrganiserCannotUpdateOthersEvent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organiser]);
        $otherOrganiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $owner->id, 'title' => 'Старое название']);

        $response = $this->actingAs($otherOrganiser, 'sanctum')->putJson("/api/events/{$event->id}", $this->validPayload([
            'title' => 'Чужое название',
        ]));

        $response->assertStatus(403);
        $this->assertSame('Старое название', $event->fresh()->title);
    }

    public function testAdminCanUpdateAnyEvent(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/events/{$event->id}", $this->validPayload([
            'title' => 'Изменено админом',
        ]));

        $response->assertStatus(200);
        $this->assertSame('Изменено админом', $event->fresh()->title);
    }

    public function testOrganiserCannotDeleteEvent(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id]);

        $response = $this->actingAs($organiser, 'sanctum')->deleteJson("/api/events/{$event->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function testAdminCanDeleteEvent(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/events/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }
}
