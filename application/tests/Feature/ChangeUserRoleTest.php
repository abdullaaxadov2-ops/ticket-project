<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;

class ChangeUserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCanChangeUserRole(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $targetUser = User::factory()->create(['role' => UserRole::Participant]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/users/{$targetUser->id}/role", [
            'role' => UserRole::Organiser->value,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertSame(UserRole::Organiser, $targetUser->fresh()->role);
    }

    public function testNonAdminCannotChangeUserRole(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        $targetUser = User::factory()->create(['role' => UserRole::Participant]);

        $response = $this->actingAs($participant, 'sanctum')->patchJson("/api/admin/users/{$targetUser->id}/role", [
            'role' => UserRole::Organiser->value,
        ]);

        $response->assertStatus(403);
    }

    public function testAdminCannotChangeOwnRole(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/users/{$admin->id}/role", [
            'role' => UserRole::Organiser->value,
        ]);

        $response->assertStatus(403);
    }

    public function testChangingRoleValidatesRole(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $targetUser = User::factory()->create(['role' => UserRole::Participant]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/users/{$targetUser->id}/role", [
            'role' => 'superadmin',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
        $this->assertSame(UserRole::Participant, $targetUser->fresh()->role);
    }
}
