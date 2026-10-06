<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrderRequest;
use App\Models\Event;
use App\Models\Order;
use App\Services\OrderCreationService;

class OrderCreationController extends Controller
{
    public function __invoke(CreateOrderRequest $request, Event $event, OrderCreationService $service)
    {
        $this->authorize('create', Order::class);

        $order = $service->create($request->user(), $event, $request->toDTO());

        return response()->json($order, 201);
    }
}
