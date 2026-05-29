<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Preambles;

use App\Enums\PreambleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreambleRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::enum(PreambleStatus::class)],
            'effective_date' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Preamble name is required.',
            'status.enum' => 'Status must be one of: '.implode(', ', array_column(PreambleStatus::cases(), 'value')).'.',
        ];
    }
}
