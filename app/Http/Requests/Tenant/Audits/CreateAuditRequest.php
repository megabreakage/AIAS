<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Audits;

use App\Enums\AuditScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateAuditRequest extends FormRequest
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
                Rule::unique('audits')->where('tenant_id', auth()->user()?->tenant_id),
            ],
            'checklist_id' => ['nullable', 'integer'],
            'task_type_id' => ['nullable', 'integer'],
            'scope' => ['nullable', 'string', Rule::in(array_map(fn (AuditScope $s) => $s->value, AuditScope::cases()))],
            'department_id' => ['nullable', 'integer'],
            'audit_start_date' => ['required', 'date'],
            'audit_end_date' => ['nullable', 'date', 'after_or_equal:audit_start_date'],
            'lead_auditor_id' => ['nullable', 'integer'],
            'quality_manager_id' => ['nullable', 'integer'],
            'add_appendix' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Audit name is required.',
            'name.unique' => 'An audit with this name already exists.',
            'audit_start_date.required' => 'Audit start date is required.',
            'audit_end_date.after_or_equal' => 'Audit end date must be on or after the start date.',
            'scope.in' => 'Scope must be one of: internal, external, service_provider, supplier.',
        ];
    }
}
