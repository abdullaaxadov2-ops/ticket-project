<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private const PS_URL = 'https://ps.test';
    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payment.url' => self::PS_URL,
            'services.payment.cashbox_id' => 7,
            'services.payment.secret' => self::SECRET,
        ]);
    }

    private function sign(array $data): string
    {
        ksort($data);
        $string = implode('|', array_map(
            fn ($key, $value) => "{$key}|{$value}",
            array_keys($data),
            array_values($data),
        ));

        return hash_hmac('sha256', $string, self::SECRET);
    }

    private function sendWebhook(array $data, ?string $signature = null)
    {
        return $this->postJson('/api/payments/webhook', $data, [
            'X-Signature' => $signature ?? $this->sign($data),
        ]);
    }

    public function testParticipantGetsPaymentUrl(): void
    {
        Http::fake([
            self::PS_URL . '/api/create-payment'
            => Http::response(['data' => ['url' => self::PS_URL . '/process-payment/15']], 201),
        ]);
        $order = Order::factory()->create(['total_amount' => 450000]);

        $response = $this->actingAs($order->user, 'sanctum')->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.payment_url', self::PS_URL . '/process-payment/15');

        $order->refresh();
        $this->assertNotNull($order->payment_reference);
        $this->assertSame(self::PS_URL . '/process-payment/15', $order->payment_url);

        Http::assertSent(function (Request $request) use ($order) {
            $expected = [
                'cashbox_id' => 7,
                'order_id' => $order->payment_reference,
                'description' => "Заказ #{$order->id}",
                'amount' => 45000000,
            ];

            return $request->url() === self::PS_URL . '/api/create-payment'
                && $request->data() === $expected
                && $request->header('X-Signature')[0] === $this->sign($expected);
        });
    }

    public function testRepeatedPayReturnsSameUrl(): void
    {
        Http::fake([
            self::PS_URL . '/api/create-payment' => Http::response(['data' => ['url' => self::PS_URL . '/process-payment/15']], 201),
        ]);
        $order = Order::factory()->create();

        $this->actingAs($order->user, 'sanctum')->postJson("/api/orders/{$order->id}/pay")->assertStatus(200);
        $response = $this->actingAs($order->user, 'sanctum')->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(200);
        $response->assertJsonPath('data.payment_url', self::PS_URL . '/process-payment/15');
        Http::assertSentCount(1);
    }

    public function testCannotPayForeignOrder(): void
    {
        Http::fake();
        $order = Order::factory()->create();
        $stranger = User::factory()->create(['role' => UserRole::Participant]);

        $response = $this->actingAs($stranger, 'sanctum')->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(403);
        Http::assertNothingSent();
    }

    public function testGuestCannotPay(): void
    {
        Http::fake();
        $order = Order::factory()->create();

        $response = $this->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(401);
        Http::assertNothingSent();
    }

    public function testCannotPayNotPendingOrder(): void
    {
        Http::fake();
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $response = $this->actingAs($order->user, 'sanctum')->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order']);
        Http::assertNothingSent();
    }

    public function testPaymentSystemErrorReturns502(): void
    {
        Http::fake([
            self::PS_URL . '/*' => Http::response(['message' => 'Server error'], 500),
        ]);
        $order = Order::factory()->create();

        $response = $this->actingAs($order->user, 'sanctum')->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(502);
        $response->assertJsonPath('success', false);
        $this->assertNull($order->fresh()->payment_url);
    }

    public function testSuccessfulWebhookMarksOrderPaid(): void
    {
        $order = Order::factory()->create(['total_amount' => 450000, 'payment_reference' => 'ref-1']);

        $response = $this->sendWebhook(['order_id' => 'ref-1', 'amount' => 45000000, 'status' => 1]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function testFailedWebhookCancelsOrderAndReleasesTickets(): void
    {
        $order = Order::factory()->create(['total_amount' => 200000, 'payment_reference' => 'ref-1']);
        $ticketType = TicketType::factory()->create(['event_id' => $order->event_id, 'price' => 100000, 'sold_count' => 3]);
        $order->items()->create(['ticket_type_id' => $ticketType->id, 'quantity' => 2, 'price' => 100000]);

        $response = $this->sendWebhook(['order_id' => 'ref-1', 'amount' => 20000000, 'status' => 2]);

        $response->assertStatus(200);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(1, $ticketType->fresh()->sold_count);
    }

    public function testWebhookWithInvalidSignatureIsRejected(): void
    {
        $order = Order::factory()->create(['total_amount' => 450000, 'payment_reference' => 'ref-1']);

        $response = $this->sendWebhook(['order_id' => 'ref-1', 'amount' => 45000000, 'status' => 1], 'fake-signature');

        $response->assertStatus(403);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function testWebhookForUnknownOrderReturns404(): void
    {
        $response = $this->sendWebhook(['order_id' => 'unknown', 'amount' => 100, 'status' => 1]);

        $response->assertStatus(404);
    }

    public function testRepeatedWebhookDoesNotChangeProcessedOrder(): void
    {
        $order = Order::factory()->create([
            'total_amount' => 100000,
            'payment_reference' => 'ref-1',
            'status' => OrderStatus::Paid,
        ]);
        $ticketType = TicketType::factory()->create(['event_id' => $order->event_id, 'price' => 100000, 'sold_count' => 1]);
        $order->items()->create(['ticket_type_id' => $ticketType->id, 'quantity' => 1, 'price' => 100000]);

        $response = $this->sendWebhook(['order_id' => 'ref-1', 'amount' => 10000000, 'status' => 2]);

        $response->assertStatus(200);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(1, $ticketType->fresh()->sold_count);
    }
}
