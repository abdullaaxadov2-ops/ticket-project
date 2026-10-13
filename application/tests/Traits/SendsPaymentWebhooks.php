<?php

namespace Tests\Traits;

use Illuminate\Testing\TestResponse;

trait SendsPaymentWebhooks
{
    // Подпись по правилам платёжной системы: поля по алфавиту, "ключ|значение|...", HMAC-SHA256
    protected function signPaymentData(array $data): string
    {
        ksort($data);
        $string = implode('|', array_map(
            fn ($key, $value) => "{$key}|{$value}",
            array_keys($data),
            array_values($data),
        ));

        return hash_hmac('sha256', $string, config('services.payment.secret'));
    }

    protected function sendPaymentWebhook(array $data): TestResponse
    {
        return $this->postJson('/api/payments/webhook', $data, [
            'X-Signature' => $this->signPaymentData($data),
        ]);
    }
}
