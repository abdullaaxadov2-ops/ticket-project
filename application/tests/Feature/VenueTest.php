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

    private function venueData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Название места проведения мероприятия',
            'address' => 'г. Ташкент, ул. Укчи, 3',
            'description' => 'Главный зал',
            'capacity' => 300,
        ], $overrides);
    }

    public function testAnyoneCanListVenues(): void
    {
        Venue::factory()->count(3)->create();

        $response = $this->getJson('/api/venues');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function testAdminCanCreateVenue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/venues', $this->venueData());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.capacity', 300);
        $this->assertDatabaseHas('venues', ['name' => 'Название места проведения мероприятия']);
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

    public function testVenueCapacityMustBePositive(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/venues', $this->venueData(['capacity' => 0]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['capacity']);
    }

    public function testNonAdminCannotCreateVenue(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson('/api/venues', $this->venueData());

        $response->assertStatus(403);
        $this->assertDatabaseCount('venues', 0);
    }

    public function testGuestCannotCreateVenue(): void
    {
        $response = $this->postJson('/api/venues', $this->venueData());

        $response->assertStatus(401);
    }

    public function testAdminCanUpdateVenue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $venue = Venue::factory()->create(['capacity' => 100]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/venues/{$venue->id}", $this->venueData([
            'capacity' => 200,
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('data.capacity', 200);
        $this->assertSame(200, $venue->fresh()->capacity);
    }

    public function testUpdatingVenueRequiresAllFields(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $venue = Venue::factory()->create(['capacity' => 100]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/venues/{$venue->id}", [
            'capacity' => 200,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'address']);
        $this->assertSame(100, $venue->fresh()->capacity);
    }

    public function testNonAdminCannotUpdateVenue(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $venue = Venue::factory()->create(['capacity' => 100]);

        $response = $this->actingAs($organiser, 'sanctum')->putJson("/api/venues/{$venue->id}", $this->venueData([
            'capacity' => 200,
        ]));

        $response->assertStatus(403);
        $this->assertSame(100, $venue->fresh()->capacity);
    }

    public function testAdminCanDeleteVenue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $venue = Venue::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/venues/{$venue->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseMissing('venues', ['id' => $venue->id]);
    }

    public function testNonAdminCannotDeleteVenue(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $venue = Venue::factory()->create();

        $response = $this->actingAs($organiser, 'sanctum')->deleteJson("/api/venues/{$venue->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('venues', ['id' => $venue->id]);
    }
}
