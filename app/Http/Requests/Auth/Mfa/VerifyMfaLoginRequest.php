<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\Mfa;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyMfaLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mfa_token' => ['required', 'string', 'uuid'],
            'code' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'mfa_token.required' => 'MFA session token is required.',
            'mfa_token.uuid' => 'Invalid MFA session token format.',
            'code.required' => 'MFA code is required.',
        ];
    }
}
