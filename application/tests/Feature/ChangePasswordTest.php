<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function testUserCanChangePassword(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->patchJson('/api/me/password', [
            'current_password' => 'password',
            'new_password' => 'new_password123',
            'new_password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertTrue(Hash::check('new_password123', $user->refresh()->password));
    }

    public function testChangePasswordFailsWithWrongCurrentPassword(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->patchJson('/api/me/password', [
            'current_password' => 'wrong-password',
            'new_password' => 'new_password123',
            'new_password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }

    public function testChangePasswordRequiresAuthentication(): void
    {
        $response = $this->patchJson('/api/me/password', [
            'current_password' => 'password',
            'new_password' => 'new_password123',
            'new_password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(401);
    }
}


