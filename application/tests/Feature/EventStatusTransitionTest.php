<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function testOrganiserCanPublishOwnDraftEvent(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id, 'status' => EventStatus::Draft]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson("/api/events/{$event->id}/publish");

        $response->assertStatus(200);
        $this->assertSame(EventStatus::Published, $event->fresh()->status);
        // assertSame это метод для сравнения $this->assertSame($expected, $actual)
    }

    public function testOrganiserCannotPublishOthersEvent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organiser]);
        $otherOrganiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $owner->id, 'status' => EventStatus::Draft]);

        $response = $this->actingAs($otherOrganiser, 'sanctum')->postJson("/api/events/{$event->id}/publish");

        $response->assertStatus(403);
    }

    public function testCannotPublishAlreadyPublishedEvent(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id, 'status' => EventStatus::Published]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson("/api/events/{$event->id}/publish");

        $response->assertStatus(422);
    }

    public function testOrganiserCanCancelOwnEvent(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id, 'status' => EventStatus::Published]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson("/api/events/{$event->id}/cancel");

        $response->assertStatus(200);
        $this->assertSame(EventStatus::Cancelled, $event->fresh()->status);
    }

    public function testAdminCanCancelAnyEvent(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::factory()->create(['status' => EventStatus::Published]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/events/{$event->id}/cancel");

        $response->assertStatus(200);
    }

    public function testCannotCancelAlreadyCancelledEvent(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $event = Event::factory()->create(['organizer_id' => $organiser->id, 'status' => EventStatus::Cancelled]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson("/api/events/{$event->id}/cancel");

        $response->assertStatus(422);
    }
}
