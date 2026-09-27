<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function testLogoutRequiresAuth(): void
    {
        $response = $this->postJson('/api/auth/logout');
        $response->assertUnauthorized();
    }

    public function testLogoutAfterTokenLogin(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('login-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/auth/logout');
        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function testLogoutAfterCookieLogin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->postJson('/api/auth/logout');
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertGuest('web');
    }
}
