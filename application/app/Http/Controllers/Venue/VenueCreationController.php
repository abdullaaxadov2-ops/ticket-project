<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venue\VenueRequest;
use App\Services\VenueService;

class VenueCreationController extends Controller
{
    public function __invoke(VenueRequest $request, VenueService $service)
    {
        $venue = $service->create($request->toDTO());

        return response()->json([
            'success' => true,
            'data' => $venue,
        ], 201);
    }
}
