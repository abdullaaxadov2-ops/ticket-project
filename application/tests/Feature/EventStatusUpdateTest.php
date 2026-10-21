<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Events\EventStarted;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class EventStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    // Опубликованное мероприятие, которое началось 5 минут назад и закончится через 2 часа
    private function createStartedEvent(array $attributes = []): Event
    {
        return Event::factory()->create([
            'status' => EventStatus::Published,
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHours(2),
            ...$attributes,
        ]);
    }

    public function testStartedEventDispatchesEventStarted(): void
    {
        EventFacade::fake([EventStarted::class]);
        $event = $this->createStartedEvent();

        $this->artisan('events:update-statuses')->assertSuccessful();

        EventFacade::assertDispatched(EventStarted::class, fn (EventStarted $started) => $started->event->id === $event->id);
        $event->refresh();
        $this->assertNotNull($event->started_at);
        $this->assertSame(EventStatus::Published, $event->status);
    }

    public function testEventStartedIsDispatchedOnce(): void
    {
        EventFacade::fake([EventStarted::class]);
        $this->createStartedEvent();

        $this->artisan('events:update-statuses')->assertSuccessful();
        $this->artisan('events:update-statuses')->assertSuccessful();

        EventFacade::assertDispatchedTimes(EventStarted::class, 1);
    }

    public function testFutureEventIsNotStarted(): void
    {
        EventFacade::fake([EventStarted::class]);
        $event = $this->createStartedEvent(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(3)]);

        $this->artisan('events:update-statuses')->assertSuccessful();

        EventFacade::assertNotDispatched(EventStarted::class);
        $this->assertNull($event->fresh()->started_at);
    }

    public function testCancelledEventIsNotStarted(): void
    {
        EventFacade::fake([EventStarted::class]);
        $this->createStartedEvent(['status' => EventStatus::Cancelled]);

        $this->artisan('events:update-statuses')->assertSuccessful();

        EventFacade::assertNotDispatched(EventStarted::class);
    }

    public function testEndedEventBecomesFinished(): void
    {
        $event = $this->createStartedEvent(['starts_at' => now()->subHours(3), 'ends_at' => now()->subMinute()]);

        $this->artisan('events:update-statuses')->assertSuccessful();

        $this->assertSame(EventStatus::Finished, $event->fresh()->status);
    }

    public function testMissedStartIsDispatchedBeforeFinish(): void
    {
        // Сервер не работал, пока мероприятие шло: событие начала всё равно не теряется
        EventFacade::fake([EventStarted::class]);
        $event = $this->createStartedEvent(['starts_at' => now()->subHours(3), 'ends_at' => now()->subMinute()]);

        $this->artisan('events:update-statuses')->assertSuccessful();

        EventFacade::assertDispatched(EventStarted::class);
        $this->assertSame(EventStatus::Finished, $event->fresh()->status);
    }

    public function testCancelledEventIsNotFinished(): void
    {
        $event = $this->createStartedEvent([
            'status' => EventStatus::Cancelled,
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subMinute(),
        ]);

        $this->artisan('events:update-statuses')->assertSuccessful();

        $this->assertSame(EventStatus::Cancelled, $event->fresh()->status);
    }

    public function testCannotBuyTicketsForFinishedEvent(): void
    {
        $event = $this->createStartedEvent(['starts_at' => now()->subHours(3), 'ends_at' => now()->subMinute()]);
        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);
        $participant = User::factory()->create(['role' => UserRole::Participant]);

        $this->artisan('events:update-statuses')->assertSuccessful();
        $response = $this->actingAs($participant, 'sanctum')->postJson("/api/events/{$event->id}/orders", [
            'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function testStartedEventIsLogged(): void
    {
        Log::spy();
        $event = $this->createStartedEvent();

        $this->artisan('events:update-statuses')->assertSuccessful();

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context) => $message === 'Мероприятие началось'
                && $context['event_id'] === $event->id)
            ->once();
    }

    public function testStatusCommandIsScheduledEveryMinute(): void
    {
        // Расписание из bootstrap/app.php регистрируется при запуске Artisan
        $this->artisan('schedule:list')->assertSuccessful();

        /** @var \Illuminate\Console\Scheduling\Event|null $scheduled */
        $scheduled = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'events:update-statuses'));

        $this->assertNotNull($scheduled);
        $this->assertSame('* * * * *', $scheduled->expression);
    }
}
