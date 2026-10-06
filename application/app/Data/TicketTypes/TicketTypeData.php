<?php

namespace App\Data\TicketTypes;

readonly class TicketTypeData
{
    public function __construct(
        public string $name,
        public string $price,
        public int $quantity,
        public ?string $description = null,
    )
    {

    }
}
