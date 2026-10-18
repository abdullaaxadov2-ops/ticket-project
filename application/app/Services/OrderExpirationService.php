<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderExpirationService
{
    public function expireOverdue(): int
    {
        // Заказы с созданным платежом не трогаем: их отменит платёжка вебхуком
        $orderIds = Order::where('status', OrderStatus::Pending)
            ->whereNull('payment_url')
            ->where('expires_at', '<=', now())
            ->pluck('id');

        $expired = 0;

        foreach ($orderIds as $orderId) {
            $expired += DB::transaction(function () use ($orderId) {
                $order = Order::lockForUpdate()->find($orderId);

                // Пока ждали блокировку, заказ могли начать оплачивать
                if ($order->status !== OrderStatus::Pending || $order->payment_url !== null) {
                    return 0;
                }

                $order->status = OrderStatus::Expired;
                $order->releaseTickets();
                $order->save();

                return 1;
            });
        }

        return $expired;
    }
}
