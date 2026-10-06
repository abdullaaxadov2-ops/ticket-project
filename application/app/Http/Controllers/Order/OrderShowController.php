<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;

class OrderShowController extends Controller
{
    public function __invoke(Order $order)
    {
        return [
            'data' => $order->load('items'),
        ];
    }
}
