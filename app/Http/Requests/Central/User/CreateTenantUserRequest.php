<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class CreateTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'preferred_timezone' => ['nullable', 'string', 'max:100'],
            'office_location' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'role' => ['required', 'string', 'in:tenant-admin,auditor,client,viewer'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'username.required' => 'Username is required.',
            'email.required' => 'Email address is required.',
            'password.required' => 'Password is required.',
            'role.required' => 'A role must be assigned to the user.',
            'role.in' => 'Role must be one of: tenant-admin, auditor, client, viewer.',
        ];
    }
}
