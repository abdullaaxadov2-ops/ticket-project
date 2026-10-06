<?php

namespace App\Http\Requests\Category;

use App\Data\Categories\CategoryData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($categoryId)],
            'description' => ['nullable', 'string'],
        ];
    }

    public function toDTO(): CategoryData
    {
        return new CategoryData(...$this->validated());
    }
}
