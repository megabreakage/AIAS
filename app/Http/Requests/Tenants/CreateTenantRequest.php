<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenants;

use Illuminate\Foundation\Http\FormRequest;

final class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate check done in route middleware (super_admin guard)
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:central.tenants,name'],
            'owner_id' => ['required', 'integer', 'exists:central.users,id'],
            'domain' => ['nullable', 'string', 'max:255', 'unique:central.tenants,domain'],
            'logo' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer'],
            'data_center' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive,suspended,pending_setup'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A tenant with this name already exists.',
            'owner_id.exists' => 'The specified owner does not exist.',
            'domain.unique' => 'This domain is already in use by another tenant.',
        ];
    }
}
