<?php

namespace App\Http\Requests\Event;

use App\Data\Events\EventFilterData;
use Illuminate\Foundation\Http\FormRequest;

class EventListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'venue_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'in:date,-date,price,-price'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toDTO(): EventFilterData
    {
        return new EventFilterData(...$this->validated());
    }
}
