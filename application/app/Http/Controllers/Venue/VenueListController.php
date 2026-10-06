<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Services\VenueService;

class VenueListController extends Controller
{
    public function __invoke(VenueService $service)
    {
        return [
            'data' => $service->getList(),
        ];
    }
}
