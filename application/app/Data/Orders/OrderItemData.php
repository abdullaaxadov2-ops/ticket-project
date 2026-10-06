<?php

namespace App\Data\Orders;

readonly class OrderItemData
{
    public function __construct(
        public int $ticket_type_id,
        public int $quantity,
    )
    {

    }
}
