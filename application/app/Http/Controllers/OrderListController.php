<?php

namespace App\Http\Controllers;

use App\Services\OrderListService;
use Illuminate\Http\Request;

class OrderListController extends Controller
{
    public function __invoke(Request $request, OrderListService $service)
    {
        return $service->list($request->user());
    }
}
