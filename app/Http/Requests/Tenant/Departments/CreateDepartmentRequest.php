<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Departments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateDepartmentRequest extends FormRequest
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
                Rule::unique('departments')->where('tenant_id', auth()->user()?->tenant_id),
            ],
            'address' => ['nullable', 'string'],
            'office_location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer'],
            'department_head' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'department_members' => ['nullable', 'array'],
            'department_members.*.user_id' => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Department name is required.',
            'name.unique' => 'A department with this name already exists.',
        ];
    }
}
