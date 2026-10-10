<?php

namespace App\Data\Payments;

readonly class PaymentWebhookData
{
    public function __construct(
        public string $order_id,
        public int $amount,
        public int $status,
    )
    {

    }
}
