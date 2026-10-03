<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventSearchTest extends TestCase
{
    use RefreshDatabase;

    public function testSearchByTitle(): void
    {
        Event::factory()->create(['title' => 'Alif 365']);
        Event::factory()->create(['title' => 'PHP diploma defense 2026']);

        $response = $this->getJson('/api/events?search=alif');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Alif 365');
    }

    public function testFilterByCategory(): void
    {
        $conference = Category::factory()->create();
        Event::factory()->count(2)->create(['category_id' => $conference->id]);
        Event::factory()->create();

        $response = $this->getJson("/api/events?category_id={$conference->id}");

        $response->assertJsonCount(2, 'data');
    }

    public function testFilterByVenue(): void
    {
        $venue = Venue::factory()->create();
        Event::factory()->create(['venue_id' => $venue->id]);
        Event::factory()->count(2)->create();

        $response = $this->getJson("/api/events?venue_id={$venue->id}");

        $response->assertJsonCount(1, 'data');
    }

    public function testFilterByDateRange(): void
    {
        Event::factory()->create(['starts_at' => '2026-11-10 10:00:00', 'ends_at' => '2026-11-10 18:00:00']);
        Event::factory()->create(['starts_at' => '2026-12-20 10:00:00', 'ends_at' => '2026-12-20 18:00:00']);

        $response = $this->getJson('/api/events?date_from=2026-11-01&date_to=2026-11-30');

        $response->assertJsonCount(1, 'data');
    }

    public function testSortByDateDescending(): void
    {
        Event::factory()->create(['title' => 'Early', 'starts_at' => '2026-11-01 10:00:00', 'ends_at' => '2026-11-01 18:00:00']);
        Event::factory()->create(['title' => 'Late', 'starts_at' => '2026-12-01 10:00:00', 'ends_at' => '2026-12-01 18:00:00']);

        $response = $this->getJson('/api/events?sort=-date');

        $response->assertJsonPath('data.0.title', 'Late');
        $response->assertJsonPath('data.1.title', 'Early');
    }

    public function testSortByMinTicketPrice(): void
    {
        $expensive = Event::factory()->create(['title' => 'Expensive']);
        $cheap = Event::factory()->create(['title' => 'Cheap']);
        TicketType::factory()->create(['event_id' => $expensive->id, 'price' => 300000]);
        TicketType::factory()->create(['event_id' => $cheap->id, 'price' => 50000]);
        TicketType::factory()->create(['event_id' => $cheap->id, 'price' => 400000]);

        $response = $this->getJson('/api/events?sort=price');

        $response->assertJsonPath('data.0.title', 'Cheap');
        $response->assertJsonPath('data.1.title', 'Expensive');
    }

    public function testInvalidSortValueFails(): void
    {
        $response = $this->getJson('/api/events?sort=hacker');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sort']);
    }
}
