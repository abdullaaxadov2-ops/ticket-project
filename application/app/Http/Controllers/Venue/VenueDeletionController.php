<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Services\VenueService;

class VenueDeletionController extends Controller
{
    public function __invoke(Venue $venue, VenueService $service)
    {
        $service->delete($venue);

        return ['success' => true];
    }
}
