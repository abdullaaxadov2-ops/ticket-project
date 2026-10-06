<?php

namespace App\Http\Requests\Venue;

use App\Data\Venues\VenueData;
use Illuminate\Foundation\Http\FormRequest;

class VenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'capacity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toDTO(): VenueData
    {
        return new VenueData(...$this->validated());
    }
}
