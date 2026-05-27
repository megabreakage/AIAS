<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\SectionStyle;

use Illuminate\Foundation\Http\FormRequest;

final class CreateSectionStyleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate check done in controller
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:central.section_styles,name'],
            'description' => ['nullable', 'string'],
            'columns' => ['nullable', 'integer', 'min:1', 'max:12'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }

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
