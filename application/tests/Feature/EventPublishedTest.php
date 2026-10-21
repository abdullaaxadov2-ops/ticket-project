<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Events\EventPublished;
use App\Listeners\LogEventPublished;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class EventPublishedTest extends TestCase
{
    use RefreshDatabase;

    private User $organiser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organiser = User::factory()->create(['role' => UserRole::Organiser]);
    }

    private function createEvent(EventStatus $status): Event
    {
        return Event::factory()->create(['organizer_id' => $this->organiser->id, 'status' => $status]);
    }

    public function testPublishingDispatchesEventPublished(): void
    {
        EventFacade::fake([EventPublished::class]);
        $event = $this->createEvent(EventStatus::Draft);

        $this->actingAs($this->organiser, 'sanctum')->postJson("/api/events/{$event->id}/publish")->assertStatus(200);

        EventFacade::assertDispatched(EventPublished::class, fn (EventPublished $published) => $published->event->id === $event->id);
    }

    public function testFailedPublishingDoesNotDispatchEventPublished(): void
    {
        EventFacade::fake([EventPublished::class]);
        $event = $this->createEvent(EventStatus::Published);

        $this->actingAs($this->organiser, 'sanctum')->postJson("/api/events/{$event->id}/publish")->assertStatus(422);

        EventFacade::assertNotDispatched(EventPublished::class);
    }

    public function testEventPublishedHasLogListener(): void
    {
        EventFacade::fake();

        EventFacade::assertListening(EventPublished::class, LogEventPublished::class);
    }

    public function testPublishedEventIsLogged(): void
    {
        Log::spy();
        $event = $this->createEvent(EventStatus::Draft);

        $this->actingAs($this->organiser, 'sanctum')->postJson("/api/events/{$event->id}/publish")->assertStatus(200);

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context) => $message === 'Мероприятие опубликовано'
                && $context['event_id'] === $event->id
                && $context['organizer_id'] === $this->organiser->id)
            ->once();
    }
}
