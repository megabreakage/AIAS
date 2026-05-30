<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\Mfa;

use Illuminate\Foundation\Http\FormRequest;

final class DisableMfaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'MFA code is required.',
            'password.required' => 'Current password is required.',
        ];
    }
}
