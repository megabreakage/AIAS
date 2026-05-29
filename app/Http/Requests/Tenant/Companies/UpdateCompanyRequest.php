<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Companies;

use App\Enums\ContactType;
use App\Enums\LevelOfOperations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate check done in controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('companies')
                    ->where('tenant_id', auth()->user()?->tenant_id)
                    ->ignore($this->route('identifier'), 'identifier'),
            ],
            'address' => ['sometimes', 'nullable', 'string'],
            'office_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country_id' => ['sometimes', 'nullable', 'integer'],
            'level_of_operations' => ['sometimes', 'nullable', Rule::enum(LevelOfOperations::class)],
            'trading_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'logo' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'is_featured' => ['sometimes', 'nullable', 'boolean'],
            'company_contacts' => ['sometimes', 'nullable', 'array'],
            'company_contacts.*.user_id' => ['nullable', 'integer'],
            'company_contacts.*.contact_type' => ['nullable', Rule::enum(ContactType::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Company name is required.',
            'name.unique' => 'A company with this name already exists.',
            'website.url' => 'The website must be a valid URL.',
            'email.email' => 'The email must be a valid email address.',
            'logo.image' => 'The logo must be an image file.',
            'logo.max' => 'The logo may not be greater than 2MB.',
        ];
    }
}
