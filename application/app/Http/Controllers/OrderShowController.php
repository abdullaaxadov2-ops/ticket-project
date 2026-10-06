<?php

namespace App\Http\Controllers;

use App\Models\Order;

class OrderShowController extends Controller
{
    public function __invoke(Order $order)
    {
        $this->authorize('view', $order);

        return $order->load('items');
    }
}
