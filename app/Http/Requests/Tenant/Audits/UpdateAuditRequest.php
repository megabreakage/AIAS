<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Audits;

use App\Enums\AuditScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAuditRequest extends FormRequest
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
                Rule::unique('audits')
                    ->where('tenant_id', auth()->user()?->tenant_id)
                    ->ignore($this->route('identifier'), 'identifier'),
            ],
            'checklist_id' => ['sometimes', 'nullable', 'integer'],
            'task_type_id' => ['sometimes', 'nullable', 'integer'],
            'scope' => ['sometimes', 'nullable', 'string', Rule::in(array_map(fn (AuditScope $s) => $s->value, AuditScope::cases()))],
            'department_id' => ['sometimes', 'nullable', 'integer'],
            'audit_start_date' => ['sometimes', 'required', 'date'],
            'audit_end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:audit_start_date'],
            'lead_auditor_id' => ['sometimes', 'nullable', 'integer'],
            'quality_manager_id' => ['sometimes', 'nullable', 'integer'],
            'add_appendix' => ['sometimes', 'nullable', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_featured' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Audit name is required.',
            'name.unique' => 'An audit with this name already exists.',
            'audit_end_date.after_or_equal' => 'Audit end date must be on or after the start date.',
            'scope.in' => 'Scope must be one of: internal, external, service_provider, supplier.',
        ];
    }
}
