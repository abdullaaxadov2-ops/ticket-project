<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => [$required, 'date', 'after:now'],
            'ends_at' => [$required, 'date', 'after:starts_at'],
            'category_id' => [$required, 'exists:categories,id'],
            'venue_id' => [$required, 'exists:venues,id'],
        ];
    }
}
