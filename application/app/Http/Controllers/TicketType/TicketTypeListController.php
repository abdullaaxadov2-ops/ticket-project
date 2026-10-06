<?php

namespace App\Http\Controllers\TicketType;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\TicketTypeService;

class TicketTypeListController extends Controller
{
    public function __invoke(Event $event, TicketTypeService $service)
    {
        return [
            'data' => $service->getList($event),
        ];
    }
}
