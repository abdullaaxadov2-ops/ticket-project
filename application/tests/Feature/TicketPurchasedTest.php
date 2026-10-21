<?php

namespace Tests\Feature;

use App\Events\TicketPurchased;
use App\Listeners\NotifyOrganiserAboutPurchase;
use App\Mail\NewPurchaseMail;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\SendsPaymentWebhooks;

class TicketPurchasedTest extends TestCase
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

    public function testPaidWebhookDispatchesTicketPurchasedOnce(): void
    {
        Event::fake([TicketPurchased::class]);
        $order = $this->createPendingOrder();

        $this->sendPaymentWebhook(self::PAID_WEBHOOK)->assertStatus(200);

        Event::assertDispatchedTimes(TicketPurchased::class, 1);
        Event::assertDispatched(TicketPurchased::class, fn (TicketPurchased $event) => $event->order->id === $order->id);
    }

    public function testFailedWebhookDoesNotDispatchTicketPurchased(): void
    {
        Event::fake([TicketPurchased::class]);
        $this->createPendingOrder();

        $this->sendPaymentWebhook([...self::PAID_WEBHOOK, 'status' => 2])->assertStatus(200);

        Event::assertNotDispatched(TicketPurchased::class);
    }

    public function testTicketPurchasedHasOrganiserListener(): void
    {
        Event::fake();

        Event::assertListening(TicketPurchased::class, NotifyOrganiserAboutPurchase::class);
    }

    public function testOrganiserNotificationGoesToQueue(): void
    {
        Queue::fake();
        $this->createPendingOrder();

        $this->sendPaymentWebhook(self::PAID_WEBHOOK)->assertStatus(200);

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === NotifyOrganiserAboutPurchase::class);
    }

    public function testOrganiserReceivesNewPurchaseEmail(): void
    {
        Mail::fake();
        $order = $this->createPendingOrder();

        $this->sendPaymentWebhook(self::PAID_WEBHOOK)->assertStatus(200);

        Mail::assertSent(NewPurchaseMail::class, fn (NewPurchaseMail $mail) => $mail->hasTo($order->event->organizer->email));
    }

    public function testNewPurchaseEmailContent(): void
    {
        $order = $this->createPendingOrder();

        $mail = new NewPurchaseMail($order);

        $mail->assertSeeInHtml('Новая покупка');
        $mail->assertSeeInHtml($order->event->title);
        $mail->assertSeeInHtml('Билетов: 2');
    }
}
