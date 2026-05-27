<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\SectionStyles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateSectionStyleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate check done in controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('section_styles')->where('tenant_id', auth()->user()?->tenant_id),
            ],
            'description' => ['nullable', 'string'],
            'columns' => ['nullable', 'integer', 'min:1', 'max:12'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Section style name is required.',
            'name.unique' => 'A section style with this name already exists.',
            'columns.min' => 'Columns must be at least 1.',
            'columns.max' => 'Columns must not exceed 12.',
        ];
    }
}
