<?php

namespace App\Console\Commands;

use App\Services\OrderExpirationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire')]
#[Description('Переводит неоплаченные просроченные заказы в expired и возвращает билеты в остаток')]
class ExpireOrders extends Command
{
    public function handle(OrderExpirationService $service): int
    {
        $count = $service->expireOverdue();

        $this->info("Просрочено заказов: {$count}");

        return self::SUCCESS;
    }
}
