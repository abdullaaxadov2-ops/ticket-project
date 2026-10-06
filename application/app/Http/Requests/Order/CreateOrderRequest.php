<?php

namespace App\Http\Requests\Order;

use App\Data\Orders\CreateOrderData;
use App\Data\Orders\OrderItemData;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_id' => ['required', 'integer', 'distinct', 'exists:ticket_types,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function toDTO(): CreateOrderData
    {
        return new CreateOrderData(
            items: array_map(
                fn (array $item) => new OrderItemData(...$item),
                $this->validated('items'),
            ),
        );
    }
}
