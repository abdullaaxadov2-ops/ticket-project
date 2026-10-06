<?php

namespace App\Data\Venues;

readonly class VenueData
{
    public function __construct(
        public string $name,
        public string $address,
        public int $capacity,
        public ?string $description = null,
    )
    {

    }
}
