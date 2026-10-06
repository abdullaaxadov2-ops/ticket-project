<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\EventRequest;
use App\Services\EventService;

class EventCreationController extends Controller
{
    public function __invoke(EventRequest $request, EventService $service)
    {
        $event = $service->create($request->user(), $request->toDTO());

        return response()->json([
            'success' => true,
            'data' => $event,
        ], 201);
    }
}
