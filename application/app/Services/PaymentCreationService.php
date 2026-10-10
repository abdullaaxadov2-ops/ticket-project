<?php

namespace App\Services;

use App\Contracts\SignatureContract;
use App\Data\Payments\PaymentData;
use App\Enums\OrderStatus;
use App\Exceptions\PaymentSystemException;
use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentCreationService
{
    public function __construct(
        readonly private SignatureContract $signatureService,
    )
    {

    }

    public function createPayment(Order $order): string
    {
        if ($order->status !== OrderStatus::Pending) {
            throw ValidationException::withMessages([
                'order' => 'Оплатить можно только заказ, ожидающий оплаты.',
            ]);
        }

        if ($order->payment_url !== null) {
            return $order->payment_url;
        }

        $data = new PaymentData(
            cashbox_id: config('services.payment.cashbox_id'),
            order_id: (string) Str::uuid(),
            description: "Заказ #{$order->id}",
            amount: $this->toTiyins($order->total_amount),
        );

        $signature = $this->signatureService->sign((array) $data, config('services.payment.secret'));

        try {
            $response = Http::withHeaders(['X-Signature' => $signature])
                ->post(config('services.payment.url') . '/api/create-payment', (array) $data)
                ->throw();
        } catch (ConnectionException|RequestException) {
            throw new PaymentSystemException();
        }

        $order->payment_reference = $data->order_id;
        $order->payment_url = $response->json('data.url');
        $order->save();

        return $order->payment_url;
    }

    private function toTiyins(string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
