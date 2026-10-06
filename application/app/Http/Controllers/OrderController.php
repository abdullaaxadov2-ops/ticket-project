<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\CreateOrderRequest;
use App\Models\Event;
use App\Models\Order;
use App\Services\OrderCreationService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::with('items')->latest();

        if ($user->isOrganiser()) {
            $query->whereHas('event', fn ($q) => $q->where('organizer_id', $user->id));
        } elseif (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        return $query->paginate(15);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return $order->load('items');
    }

    public function store(CreateOrderRequest $request, Event $event, OrderCreationService $orderService)
    {
        $this->authorize('create', Order::class);

        $order = $orderService->create($request->user(), $event, $request->validated('items'));

        return response()->json($order, 201);
    }
}
