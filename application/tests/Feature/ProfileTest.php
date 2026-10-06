<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function testUserCanSeeOwnProfile(): void
    {
        $user = User::factory()->create(['name' => 'Ёкубжон']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.name', 'Ёкубжон');
        $response->assertJsonMissingPath('data.password');
    }

    public function testGuestCannotSeeProfile(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function testUserCanChangeName(): void
    {
        $user = User::factory()->create(['name' => 'Старое имя']);

        $response = $this->actingAs($user, 'sanctum')->patchJson('/api/me/name', [
            'name' => 'Новое имя',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Новое имя');
        $this->assertSame('Новое имя', $user->fresh()->name);
    }

    public function testChangingNameRequiresName(): void
    {
        $user = User::factory()->create(['name' => 'Старое имя']);

        $response = $this->actingAs($user, 'sanctum')->patchJson('/api/me/name', [
            'name' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
        $this->assertSame('Старое имя', $user->fresh()->name);
    }

    public function testGuestCannotChangeName(): void
    {
        $response = $this->patchJson('/api/me/name', [
            'name' => 'Новое имя',
        ]);

        $response->assertStatus(401);
    }
}
