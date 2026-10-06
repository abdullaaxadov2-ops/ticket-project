<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function testAnyoneCanListCategories(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function testAdminCanCreateCategory(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/categories', [
            'name' => 'Конференции',
            'description' => 'IT-конференции и митапы',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Конференции');
        $this->assertDatabaseHas('categories', ['name' => 'Конференции']);
    }

    public function testCreatingCategoryWithoutNameFails(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/categories', [
            'description' => 'Без названия',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function testCategoryNameMustBeUnique(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Category::factory()->create(['name' => 'Концерты']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/categories', [
            'name' => 'Концерты',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function testNonAdminCannotCreateCategory(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);

        $response = $this->actingAs($organiser, 'sanctum')->postJson('/api/categories', [
            'name' => 'Концерты',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('categories', 0);
    }

    public function testGuestCannotCreateCategory(): void
    {
        $response = $this->postJson('/api/categories', [
            'name' => 'Концерты',
        ]);

        $response->assertStatus(401);
    }

    public function testAdminCanUpdateCategory(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $category = Category::factory()->create(['name' => 'Старое имя']);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/categories/{$category->id}", [
            'name' => 'Новое имя',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Новое имя');
        $this->assertSame('Новое имя', $category->fresh()->name);
    }

    public function testAdminCanUpdateCategoryKeepingSameName(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $category = Category::factory()->create(['name' => 'Концерты']);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/categories/{$category->id}", [
            'name' => 'Концерты',
            'description' => 'Новое описание',
        ]);

        $response->assertStatus(200);
        $this->assertSame('Новое описание', $category->fresh()->description);
    }

    public function testNonAdminCannotUpdateCategory(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $category = Category::factory()->create(['name' => 'Старое имя']);

        $response = $this->actingAs($organiser, 'sanctum')->putJson("/api/categories/{$category->id}", [
            'name' => 'Новое имя',
        ]);

        $response->assertStatus(403);
        $this->assertSame('Старое имя', $category->fresh()->name);
    }

    public function testAdminCanDeleteCategory(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $category = Category::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function testNonAdminCannotDeleteCategory(): void
    {
        $organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $category = Category::factory()->create();

        $response = $this->actingAs($organiser, 'sanctum')->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
