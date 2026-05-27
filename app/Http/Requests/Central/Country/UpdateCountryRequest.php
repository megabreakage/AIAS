<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Country;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('central.countries', 'name')->ignore(
                    $this->route('identifier'),
                    'identifier',
                ),
            ],
            'slug' => ['nullable', 'string', 'max:255'],
            'continent_id' => ['sometimes', 'required', 'integer', 'exists:central.continents,id'],
            'short_code' => ['nullable', 'string', 'max:10'],
            'iso_code' => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'max:5'],
            'currency_name' => ['nullable', 'string', 'max:50'],
            'currency_sign' => ['nullable', 'string', 'max:5'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'phone_digits' => ['nullable', 'integer', 'min:1', 'max:15'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Country name required.',
            'name.unique' => 'Country with this name already exists.',
            'continent_id.required' => 'Continent required.',
            'continent_id.exists' => 'Selected continent does not exist.',
            'short_code.max' => 'Short code must not exceed 10 characters.',
            'iso_code.max' => 'ISO code must not exceed 10 characters.',
            'currency.max' => 'Currency code must not exceed 5 characters.',
            'currency_name.max' => 'Currency name must not exceed 50 characters.',
            'currency_sign.max' => 'Currency sign must not exceed 5 characters.',
            'country_code.max' => 'Country code must not exceed 10 characters.',
            'phone_digits.max' => 'Phone digits must not exceed 15.',
        ];
    }
}
