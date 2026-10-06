<?php

namespace App\Data\Events;

readonly class EventFilterData
{
    public function __construct(
        public ?string $search = null,
        public ?int $category_id = null,
        public ?int $venue_id = null,
        public ?string $date_from = null,
        public ?string $date_to = null,
        public ?string $sort = null,
        public ?int $per_page = null,
    )
    {

    }
}
