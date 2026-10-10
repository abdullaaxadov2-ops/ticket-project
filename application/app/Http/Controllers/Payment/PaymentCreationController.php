<?php

namespace App\Http\Controllers\Payment;

use App\Exceptions\PaymentSystemException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentCreationService;

class PaymentCreationController extends Controller
{
    public function __invoke(Order $order, PaymentCreationService $service)
    {
        try {
            $url = $service->createPayment($order);
        } catch (PaymentSystemException) {
            return response()->json([
                'success' => false,
                'message' => 'Платёжная система недоступна, попробуйте позже.',
            ], 502);
        }

        return [
            'success' => true,
            'data' => ['payment_url' => $url],
        ];
    }
}
