<?php

namespace Tests\Feature;

use App\Models\Venue;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueTest extends TestCase
{
    use RefreshDatabase;

    public function testAnyoneCanListVenues(): void
    {
        Venue::factory()->count(3)->create();

        $response = $this->getJson('/api/venues');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
    }

    public function testAdminCanCreateVenue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/venues', [
            'name' => 'Конференц-зал "А"',
            'address' => 'г. Ташкент, ул. Амира Темура, 1',
            'description' => 'Главный зал',
            'capacity' => 300,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('venues', ['name' => 'Конференц-зал "А"']);
    }

    public function testCreatingVenueWithoutRequiredFieldsFails(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/venues', [
            'description' => 'Без названия и адреса',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'address', 'capacity']);
    }

    public function testNonAdminCannotCreateVenue(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson('/api/venues', [
            'name' => 'Зал Б',
            'address' => 'ул. Тестовая, 2',
            'capacity' => 100,
        ]);

        $response->assertStatus(403);
    }

    public function testAdminCanUpdateVenue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $venue = Venue::factory()->create(['capacity' => 100]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/venues/{$venue->id}", [
            'capacity' => 200,
        ]);

        $response->assertStatus(200);
        $this->assertSame(200, $venue->fresh()->capacity);
    }

    public function testAdminCanDeleteVenue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $venue = Venue::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/venues/{$venue->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('venues', ['id' => $venue->id]);
    }
}
