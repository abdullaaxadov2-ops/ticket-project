<?php

namespace App\Http\Requests\Payment;

use App\Data\Payments\PaymentWebhookData;
use Illuminate\Foundation\Http\FormRequest;

class PaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string'],
            'amount' => ['required', 'integer'],
            'status' => ['required', 'integer', 'in:1,2'],
        ];
    }

    public function toDTO(): PaymentWebhookData
    {
        return new PaymentWebhookData(...$this->validated());
    }
}
