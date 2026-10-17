<?php

namespace Tests\Feature;

use App\Events\OrderPaid;
use App\Listeners\GenerateTickets;
use App\Listeners\SendPurchaseConfirmation;
use App\Mail\PurchaseConfirmationMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\SendsPaymentWebhooks;

class PurchaseConfirmationTest extends TestCase
{
    use RefreshDatabase, SendsPaymentWebhooks;

    private const PAID_WEBHOOK = ['order_id' => 'ref-1', 'amount' => 20000000, 'status' => 1];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payment.secret' => 'test-secret']);
    }

    // Заказ, ожидающий оплаты: Standard × 2
    private function createPendingOrder(): Order
    {
        $order = Order::factory()->create(['total_amount' => 200000, 'payment_reference' => 'ref-1']);
        $standard = TicketType::factory()->create(['event_id' => $order->event_id, 'price' => 100000, 'sold_count' => 2]);
        $order->items()->create(['ticket_type_id' => $standard->id, 'quantity' => 2, 'price' => 100000]);

        return $order;
    }

    public function testPaidWebhookDispatchesOrderPaidEvent(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->createPendingOrder();

        $this->sendPaymentWebhook(self::PAID_WEBHOOK)->assertStatus(200);

        Event::assertDispatched(OrderPaid::class, fn (OrderPaid $event) => $event->order->id === $order->id);
    }

    public function testFailedWebhookDoesNotDispatchOrderPaidEvent(): void
    {
        Event::fake([OrderPaid::class]);
        $this->createPendingOrder();

        $this->sendPaymentWebhook([...self::PAID_WEBHOOK, 'status' => 2])->assertStatus(200);

        Event::assertNotDispatched(OrderPaid::class);
    }

    public function testOrderPaidHasTicketAndEmailListeners(): void
    {
        Event::fake();

        Event::assertListening(OrderPaid::class, GenerateTickets::class);
        Event::assertListening(OrderPaid::class, SendPurchaseConfirmation::class);
    }

    public function testPurchaseConfirmationGoesToQueue(): void
    {
        Queue::fake();
        $this->createPendingOrder();

        $this->sendPaymentWebhook(self::PAID_WEBHOOK)->assertStatus(200);

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === SendPurchaseConfirmation::class);
    }

    public function testBuyerReceivesPurchaseConfirmationEmail(): void
    {
        Mail::fake();
        $order = $this->createPendingOrder();

        $this->sendPaymentWebhook(self::PAID_WEBHOOK)->assertStatus(200);

        Mail::assertSent(PurchaseConfirmationMail::class, fn (PurchaseConfirmationMail $mail) => $mail->hasTo($order->user->email));
    }

    public function testPurchaseConfirmationEmailContent(): void
    {
        $ticket = Ticket::factory()->create();

        $mail = new PurchaseConfirmationMail($ticket->order);

        $mail->assertSeeInHtml('Вы успешно приобрели билет на мероприятие');
        $mail->assertSeeInHtml($ticket->event->title);
        $mail->assertSeeInHtml($ticket->number);
    }
}
