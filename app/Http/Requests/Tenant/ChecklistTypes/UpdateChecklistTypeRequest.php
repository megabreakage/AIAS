<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\ChecklistTypes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateChecklistTypeRequest extends FormRequest
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
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('checklist_types')
                    ->where('tenant_id', auth()->user()?->tenant_id)
                    ->ignore($this->route('identifier'), 'identifier'),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Checklist type name is required.',
            'name.unique' => 'A checklist type with this name already exists.',
        ];
    }
}
