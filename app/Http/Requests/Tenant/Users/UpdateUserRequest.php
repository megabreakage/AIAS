<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate check done in controller
    }

    public function rules(): array
    {
        $identifier = $this->route('identifier');

        return [
            'title' => ['nullable', 'string', 'max:50'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'username' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('users', 'username')->whereNot('identifier', $identifier)],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->whereNot('identifier', $identifier)],
            'country_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', Password::min(8)->letters()->numbers()],
            'preferred_timezone' => ['nullable', 'string', 'max:100'],
            'office_location' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'username.required' => 'Username is required.',
            'username.unique' => 'This username is already taken.',
            'email.required' => 'Email address is required.',
            'email.unique' => 'A user with this email already exists.',
        ];
    }
}
