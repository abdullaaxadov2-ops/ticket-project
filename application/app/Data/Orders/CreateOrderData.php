<?php

namespace App\Data\Orders;

readonly class CreateOrderData
{
    /**
     * @param OrderItemData[] $items
     */
    public function __construct(
        public array $items,
    )
    {

    }
}
