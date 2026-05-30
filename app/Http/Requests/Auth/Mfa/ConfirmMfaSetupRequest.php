<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\Mfa;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmMfaSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:6'],
            'secret' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'TOTP code is required.',
            'code.digits' => 'TOTP code must be exactly 6 digits.',
            'secret.required' => 'MFA secret is required.',
        ];
    }
}
