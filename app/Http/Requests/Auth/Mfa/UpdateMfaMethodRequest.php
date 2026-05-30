<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\Mfa;

use App\Enums\MfaMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMfaMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::enum(MfaMethod::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => 'MFA method is required.',
            'method.enum' => 'Invalid MFA method. Allowed: totp, email.',
        ];
    }
}
