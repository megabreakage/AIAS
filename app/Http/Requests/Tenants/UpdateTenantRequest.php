<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenants;

use App\Enums\TenantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate check done in controller
    }

    public function rules(): array
    {
        $identifier = $this->route('identifier') ?? $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('central.tenants', 'name')->ignore($identifier, 'identifier'),
            ],
            'owner_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('central.users', 'identifier'),
            ],
            'domain' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('central.tenants', 'domain')->ignore($identifier, 'identifier'),
            ],
            'logo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country_id' => ['sometimes', 'nullable', 'integer'],
            'data_center' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => [
                'sometimes',
                'nullable',
                Rule::enum(TenantStatus::class),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tenant name is required.',
            'name.unique' => 'A tenant with this name already exists.',
            'owner_id.exists' => 'The specified owner does not exist.',
            'domain.unique' => 'This domain is already in use by another tenant.',
            'status.enum' => 'Status must be one of: active, inactive, suspended, pending_setup.',
        ];
    }
}
