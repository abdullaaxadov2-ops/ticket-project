<?php

namespace App\Http\Requests\TicketType;

use App\Data\TicketTypes\TicketTypeData;
use Illuminate\Foundation\Http\FormRequest;

class TicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toDTO(): TicketTypeData
    {
        return new TicketTypeData(...$this->validated());
    }
}
