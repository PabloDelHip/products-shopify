<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            '*' => ['required', 'array'],

            // Origen
            '*.provider' => ['required', 'string'],
            '*.provider_category_id' => ['required', 'string'],

            // Jerarquía
            '*.parent_id' => ['nullable', 'string'],

            // Datos base
            '*.name' => ['required', 'string'],
            '*.level' => ['required', 'integer', 'min:1'],
        ];
    }
}
