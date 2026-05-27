<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Preambles;

use App\Models\Tenant\Preamble;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePreambleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate check done in controller
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(Preamble::STATUSES)],
            'effective_date' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Preamble name is required.',
            'status.in' => 'Status must be one of: '.implode(', ', Preamble::STATUSES).'.',
        ];
    }
}
