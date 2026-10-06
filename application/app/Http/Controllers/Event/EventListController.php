<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\EventListRequest;
use App\Services\EventSearchService;

class EventListController extends Controller
{
    public function __invoke(EventListRequest $request, EventSearchService $service)
    {
        return $service->search($request->user('sanctum'), $request->toDTO());
    }
}
