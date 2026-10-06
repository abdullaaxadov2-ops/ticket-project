<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venue\VenueRequest;
use App\Models\Venue;
use App\Services\VenueService;

class VenueUpdateController extends Controller
{
    public function __invoke(VenueRequest $request, Venue $venue, VenueService $service)
    {
        return [
            'success' => true,
            'data' => $service->update($venue, $request->toDTO()),
        ];
    }
}
