<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Services\TicketService;
use Illuminate\Http\Request;

class TicketListController extends Controller
{
    public function __invoke(Request $request, TicketService $service)
    {
        return $service->getList($request->user());
    }
}
