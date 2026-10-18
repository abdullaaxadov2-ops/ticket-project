<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderExpirationTest extends TestCase
{
    use RefreshDatabase;

    // Заказ на 2 билета, тип билета уже продан в количестве 3 (2 из них — этот заказ)
    private function createOrderWithTickets(array $attributes = []): array
    {
        $order = Order::factory()->create($attributes);
        $ticketType = TicketType::factory()->create(['event_id' => $order->event_id, 'price' => 100000, 'sold_count' => 3]);
        $order->items()->create(['ticket_type_id' => $ticketType->id, 'quantity' => 2, 'price' => 100000]);

        return [$order, $ticketType];
    }

    public function testNewOrderGetsPaymentDeadline(): void
    {
        $this->freezeSecond();
        $participant = User::factory()->create(['role' => UserRole::Participant]);
        $event = Event::factory()->create(['status' => EventStatus::Published]);
        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAs($participant, 'sanctum')->postJson("/api/events/{$event->id}/orders", [
            'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(201);
        $order = Order::findOrFail($response->json('data.id'));
        $this->assertTrue($order->expires_at->equalTo(now()->addMinutes(15)));
    }

    public function testOverdueOrderIsExpiredAndTicketsReleased(): void
    {
        [$order, $ticketType] = $this->createOrderWithTickets(['expires_at' => now()->subMinute()]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(1, $ticketType->fresh()->sold_count);
    }

    public function testOrderBeforeDeadlineIsNotExpired(): void
    {
        [$order, $ticketType] = $this->createOrderWithTickets(['expires_at' => now()->addMinute()]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(3, $ticketType->fresh()->sold_count);
    }

    public function testOrderWithStartedPaymentIsNotExpired(): void
    {
        // Платёж уже создан: его отменит сама платёжка вебхуком status=2
        [$order, $ticketType] = $this->createOrderWithTickets([
            'expires_at' => now()->subMinute(),
            'payment_reference' => 'ref-1',
            'payment_url' => 'https://ps.test/process-payment/1',
        ]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(3, $ticketType->fresh()->sold_count);
    }

    public function testPaidOrderIsNotExpired(): void
    {
        [$order, $ticketType] = $this->createOrderWithTickets([
            'expires_at' => now()->subMinute(),
            'status' => OrderStatus::Paid,
        ]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(3, $ticketType->fresh()->sold_count);
    }

    public function testCannotPayOverdueOrder(): void
    {
        Http::fake();
        $order = Order::factory()->create(['expires_at' => now()->subMinute()]);

        $response = $this->actingAs($order->user, 'sanctum')->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order']);
        Http::assertNothingSent();
    }

    public function testExpireCommandIsScheduledEveryMinute(): void
    {
        // Расписание из bootstrap/app.php регистрируется при запуске Artisan
        $this->artisan('schedule:list')->assertSuccessful();

        /** @var \Illuminate\Console\Scheduling\Event|null $scheduled */
        $scheduled = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'orders:expire'));

        $this->assertNotNull($scheduled);
        $this->assertSame('* * * * *', $scheduled->expression);
    }
}
