<?php

namespace App\Services;

use App\Contracts\SignatureContract;
use App\Data\Payments\PaymentWebhookData;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidSignatureException;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class PaymentWebhookService
{
    private const STATUS_PAID = 1;

    public function __construct(
        readonly private SignatureContract $signatureService,
    )
    {

    }

    public function handle(PaymentWebhookData $data, string $signature): void
    {
        if (!$this->signatureService->checkSignature((array) $data, config('services.payment.secret'), $signature)) {
            throw new InvalidSignatureException();
        }

        DB::transaction(function () use ($data) {
            $order = Order::where('payment_reference', $data->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== OrderStatus::Pending) {
                return;
            }

            if ($data->status === self::STATUS_PAID) {
                $order->status = OrderStatus::Paid;
            } else {
                $order->status = OrderStatus::Cancelled;

                foreach ($order->items as $item) {
                    $item->ticketType()->decrement('sold_count', $item->quantity);
                }
            }

            $order->save();
        });
    }
}
