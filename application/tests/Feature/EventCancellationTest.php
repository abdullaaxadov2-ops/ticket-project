<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Listeners\NotifyTicketHolders;
use App\Mail\EventCancelledMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\SendsPaymentWebhooks;

class EventCancellationTest extends TestCase
{
    use RefreshDatabase, SendsPaymentWebhooks;

    private User $organiser;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organiser = User::factory()->create(['role' => UserRole::Organiser]);
        $this->event = Event::factory()->create(['organizer_id' => $this->organiser->id, 'status' => EventStatus::Published]);
    }

    private function cancelEvent(): TestResponse
    {
        return $this->actingAs($this->organiser, 'sanctum')->postJson("/api/events/{$this->event->id}/cancel");
    }

    // Билет оплаченного заказа на мероприятие из setUp
    private function createTicket(array $orderAttributes = []): Ticket
    {
        $order = Order::factory()->create(['event_id' => $this->event->id, 'status' => OrderStatus::Paid, ...$orderAttributes]);

        return Ticket::factory()->create(['order_id' => $order->id]);
    }

    public function testPaidOrdersRequireRefundAfterCancellation(): void
    {
        $ticket = $this->createTicket();

        $this->cancelEvent()->assertStatus(200);

        $order = $ticket->order->fresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertTrue($order->refund_required);
    }

    public function testTicketsAreCancelledWithEvent(): void
    {
        $ticket = $this->createTicket();

        $this->cancelEvent()->assertStatus(200);

        $this->assertSame(TicketStatus::Cancelled, $ticket->fresh()->status);
    }

    public function testPendingOrdersAreCancelledWithEvent(): void
    {
        $order = Order::factory()->create(['event_id' => $this->event->id]);

        $this->cancelEvent()->assertStatus(200);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertFalse($order->refund_required);
    }

    public function testOtherEventsAreNotAffected(): void
    {
        $otherTicket = Ticket::factory()->create();

        $this->cancelEvent()->assertStatus(200);

        $this->assertSame(TicketStatus::Active, $otherTicket->fresh()->status);
        $this->assertFalse($otherTicket->order->fresh()->refund_required);
    }

    public function testTicketHoldersAreNotifiedOnce(): void
    {
        Mail::fake();
        $holder = User::factory()->create();
        $this->createTicket(['user_id' => $holder->id]);
        $this->createTicket(['user_id' => $holder->id]);
        $pendingOrder = Order::factory()->create(['event_id' => $this->event->id]);

        $this->cancelEvent()->assertStatus(200);

        Mail::assertSent(EventCancelledMail::class, 1);
        Mail::assertSent(EventCancelledMail::class, fn (EventCancelledMail $mail) => $mail->hasTo($holder->email));
        Mail::assertNotSent(EventCancelledMail::class, fn (EventCancelledMail $mail) => $mail->hasTo($pendingOrder->user->email));
    }

    public function testNotificationGoesToQueue(): void
    {
        Queue::fake();

        $this->cancelEvent()->assertStatus(200);

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === NotifyTicketHolders::class);
    }

    public function testEventCancelledEmailContent(): void
    {
        $mail = new EventCancelledMail($this->event);

        $mail->assertSeeInHtml('отменено');
        $mail->assertSeeInHtml($this->event->title);
        $mail->assertSeeInHtml('возвращены');
    }

    public function testLatePaymentForCancelledOrderRequiresRefund(): void
    {
        config(['services.payment.secret' => 'test-secret']);
        $order = Order::factory()->create(['status' => OrderStatus::Cancelled, 'payment_reference' => 'ref-1']);

        $this->sendPaymentWebhook(['order_id' => 'ref-1', 'amount' => 100, 'status' => 1])->assertStatus(200);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertTrue($order->refund_required);
        $this->assertSame(0, $order->tickets()->count());
    }

    public function testLatePaymentForExpiredOrderRequiresRefund(): void
    {
        config(['services.payment.secret' => 'test-secret']);
        $order = Order::factory()->create(['status' => OrderStatus::Expired, 'payment_reference' => 'ref-1']);

        $this->sendPaymentWebhook(['order_id' => 'ref-1', 'amount' => 100, 'status' => 1])->assertStatus(200);

        $this->assertTrue($order->fresh()->refund_required);
    }
}
