<?php

namespace App\Http\Controllers\Payment;

use App\Exceptions\InvalidSignatureException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\PaymentWebhookRequest;
use App\Services\PaymentWebhookService;

class PaymentWebhookController extends Controller
{
    public function __invoke(PaymentWebhookRequest $request, PaymentWebhookService $service)
    {
        try {
            $service->handle($request->toDTO(), (string) $request->header('X-Signature'));
        } catch (InvalidSignatureException) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        return ['success' => true];
    }
}
