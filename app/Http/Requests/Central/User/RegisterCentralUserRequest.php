<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterCentralUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Guard check handled by route middleware (auth:super_admin)
    }

    public function rules(): array
    {
        return [
            'title'              => ['nullable', 'string', 'max:50'],
            'first_name'         => ['required', 'string', 'max:100'],
            'middle_name'        => ['nullable', 'string', 'max:100'],
            'last_name'          => ['required', 'string', 'max:100'],
            'username'           => ['required', 'string', 'max:100', 'unique:central.users,username'],
            'email'              => ['required', 'email', 'max:255', 'unique:central.users,email'],
            'country_code'       => ['nullable', 'string', 'max:10'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'password'           => ['required', Password::min(8)->letters()->numbers()],
            'preferred_timezone' => ['nullable', 'string', 'max:100'],
            'office_location'    => ['nullable', 'string', 'max:255'],
            'avatar'             => ['nullable', 'string', 'max:500'],
            'notes'              => ['nullable', 'string'],
            'is_active'          => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required'  => 'Last name is required.',
            'username.required'   => 'Username is required.',
            'username.unique'     => 'This username is already taken.',
            'email.required'      => 'Email address is required.',
            'email.unique'        => 'A user with this email already exists.',
            'password.required'   => 'Password is required.',
        ];
    }
}
