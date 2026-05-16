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
            'id'     => ['nullable', 'string', 'max:255', 'unique:central.tenants,id', 'regex:/^[a-z0-9_-]+$/'],
            'name'   => ['required', 'string', 'max:255'],
            'plan'   => ['nullable', 'string', 'in:starter,professional,enterprise'],
            'domain' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.regex' => 'Tenant ID may only contain lowercase letters, numbers, hyphens, and underscores.',
        ];
    }
}
