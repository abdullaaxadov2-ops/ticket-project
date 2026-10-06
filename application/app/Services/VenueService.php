<?php

namespace App\Services;

use App\Data\Venues\VenueData;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Collection;

class VenueService
{
    public function getList(): Collection
    {
        return Venue::all();
    }

    public function create(VenueData $data): Venue
    {
        return Venue::create((array) $data);
    }

    public function update(Venue $venue, VenueData $data): Venue
    {
        $venue->fill((array) $data);
        $venue->save();

        return $venue;
    }

    public function delete(Venue $venue): void
    {
        $venue->delete();
    }
}
