<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
/*
 *  проверяем, каким HTTP-методом пришёл текущий запрос.
 *  POST — поля обязательны. Любой другой метод (PATCH) -
 *  используем 'sometimes' (проверять поле, только если оно вообще присутствует в запросе,
 *  а не требовать его).
*/
        return [
            'name' => [$required, 'string', 'max:255'],
            'address' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'capacity' => [$required, 'integer', 'min:1'],
        ];
    }
}
