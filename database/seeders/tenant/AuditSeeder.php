<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Enums\AuditScope;
use App\Models\Tenant\Audit;
use App\Models\Tenant\Checklist;
use App\Models\Tenant\Department;
use Illuminate\Database\Seeder;

class AuditSeeder extends Seeder
{
    public function run(): void
    {
        $financeDept = Department::where('name', 'Finance & Accounting')->first();
        $itDept = Department::where('name', 'Information Technology')->first();
        $opsDept = Department::where('name', 'Operations')->first();
        $qaDept = Department::where('name', 'Quality Assurance')->first();

        $isoChecklist = Checklist::where('name', 'ISO 9001 Compliance Checklist')->first();
        $safetyChecklist = Checklist::where('name', 'Workplace Safety Checklist')->first();
        $financialChecklist = Checklist::where('name', 'Financial Controls Checklist')->first();
        $dataChecklist = Checklist::where('name', 'Data Protection Checklist')->first();
        $supplierChecklist = Checklist::where('name', 'Supplier Evaluation Checklist')->first();

        $audits = [
            [
                'name' => 'Annual Financial Audit 2026',
                'checklist_id' => $financialChecklist?->id,
                'scope' => AuditScope::Internal,
                'department_id' => $financeDept?->id,
                'audit_start_date' => now()->addWeek(),
                'audit_end_date' => now()->addMonth(),
                'add_appendix' => true,
                'description' => 'Comprehensive annual review of financial controls and processes.',
                'is_featured' => true,
            ],
            [
                'name' => 'ISO 9001 Compliance Audit',
                'checklist_id' => $isoChecklist?->id,
                'scope' => AuditScope::External,
                'department_id' => $qaDept?->id,
                'audit_start_date' => now()->addWeeks(2),
                'audit_end_date' => now()->addWeeks(4),
                'add_appendix' => true,
                'description' => 'External audit for ISO 9001 quality management certification.',
                'is_featured' => true,
            ],
            [
                'name' => 'Workplace Safety Audit',
                'checklist_id' => $safetyChecklist?->id,
                'scope' => AuditScope::Internal,
                'department_id' => $opsDept?->id,
                'audit_start_date' => now()->addDays(10),
                'audit_end_date' => now()->addDays(20),
                'add_appendix' => false,
                'description' => 'Internal safety inspection across all operational departments.',
                'is_featured' => false,
            ],
            [
                'name' => 'IT Security Audit',
                'checklist_id' => $dataChecklist?->id,
                'scope' => AuditScope::Internal,
                'department_id' => $itDept?->id,
                'audit_start_date' => now()->addWeeks(3),
                'audit_end_date' => now()->addWeeks(5),
                'add_appendix' => true,
                'description' => 'Assessment of IT security controls, access management, and data protection.',
                'is_featured' => false,
            ],
            [
                'name' => 'Supplier Quality Audit',
                'checklist_id' => $supplierChecklist?->id,
                'scope' => AuditScope::Supplier,
                'department_id' => $qaDept?->id,
                'audit_start_date' => now()->addMonth(),
                'audit_end_date' => now()->addMonths(2),
                'add_appendix' => false,
                'description' => 'Evaluation of key supplier quality processes and compliance.',
                'is_featured' => false,
            ],
        ];

        foreach ($audits as $data) {
            Audit::firstOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}
