<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;

class BlockUserTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCanBlockUser(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $targetUser = User::factory()->create(['role' => UserRole::Participant, 'is_blocked' => false]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/users/{$targetUser->id}/block", [
            'is_blocked' => true,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($targetUser->fresh()->is_blocked);
    }

    public function testAdminCanUnblockUser(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $targetUser = User::factory()->create(['role' => UserRole::Participant, 'is_blocked' => true]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/users/{$targetUser->id}/block", [
            'is_blocked' => false,
        ]);

        $response->assertStatus(200);
        $this->assertFalse($targetUser->fresh()->is_blocked);
    }

    public function testNonAdminCannotBlockUser(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        $targetUser = User::factory()->create(['role' => UserRole::Participant]);

        $response = $this->actingAs($participant, 'sanctum')->patchJson("/api/admin/users/{$targetUser->id}/block", [
            'is_blocked' => true,
        ]);

        $response->assertStatus(403);
    }

    public function testAdminCannotBlockSelf(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/users/{$admin->id}/block", [
            'is_blocked' => true,
        ]);

        $response->assertStatus(403);
    }
}
