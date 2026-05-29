<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Checklists;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateChecklistRequest extends FormRequest
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
                Rule::unique('checklists')
                    ->where('tenant_id', auth()->user()?->tenant_id)
                    ->ignore($this->route('identifier'), 'identifier'),
            ],
            'quality_controller_id' => ['sometimes', 'nullable', 'integer'],
            'preamble_id' => ['sometimes', 'nullable', 'integer'],
            'checklist_type_id' => ['sometimes', 'nullable', 'integer'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'is_featured' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Checklist name is required.',
            'name.unique' => 'A checklist with this name already exists.',
        ];
    }
}
