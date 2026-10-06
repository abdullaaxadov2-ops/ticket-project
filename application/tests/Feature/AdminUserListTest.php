<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserListTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCanSeeUserList(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/users');

        $response->assertStatus(200);
        $response->assertJsonCount(4, 'data');
        $response->assertJsonPath('total', 4);
        $response->assertJsonMissingPath('data.0.password');
    }

    public function testNonAdminCannotSeeUserList(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);

        $response = $this->actingAs($organiser, 'sanctum')->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function testGuestCannotSeeUserList(): void
    {
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(401);
    }
}
