<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Models\Event;
use App\Services\OrderCreationService;

class OrderCreationController extends Controller
{
    public function __invoke(CreateOrderRequest $request, Event $event, OrderCreationService $service)
    {
        $order = $service->create($request->user(), $event, $request->toDTO());

        return response()->json([
            'success' => true,
            'data' => $order,
        ], 201);
    }
}
