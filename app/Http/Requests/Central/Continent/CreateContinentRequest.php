<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Continent;

use Illuminate\Foundation\Http\FormRequest;

final class CreateContinentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate check done in controller
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:central.continents,name'],
            'slug' => ['nullable', 'string', 'max:255'],
            'short_code' => ['nullable', 'string', 'max:10'],
            'iso_code' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Continent name is required.',
            'name.unique' => 'A continent with this name already exists.',
            'short_code.max' => 'Short code must not exceed 10 characters.',
            'iso_code.max' => 'ISO code must not exceed 10 characters.',
        ];
    }
}
