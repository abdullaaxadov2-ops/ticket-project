<?php

namespace App\Data\Events;

readonly class EventData
{
    public function __construct(
        public string $title,
        public string $starts_at,
        public string $ends_at,
        public int $category_id,
        public int $venue_id,
        public ?string $description = null,
    )
    {

    }
}
