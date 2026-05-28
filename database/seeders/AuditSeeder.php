<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Audit;
use App\Models\Checklist;
use App\Models\Company;
use App\Models\Department;
use App\Models\Priority;
use Illuminate\Database\Seeder;

final class AuditSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant()?->id;

        $acme     = Company::where('code', 'ACME')->where('tenant_id', $tenantId)->first();
        $pinnacle = Company::where('code', 'PFS')->where('tenant_id', $tenantId)->first();
        $nextgen  = Company::where('code', 'NGTS')->where('tenant_id', $tenantId)->first();
        $horizon  = Company::where('code', 'HHG')->where('tenant_id', $tenantId)->first();
        $greenleaf = Company::where('code', 'GLE')->where('tenant_id', $tenantId)->first();

        $finDept  = Department::where('code', 'FIN')->where('tenant_id', $tenantId)->first();
        $hrDept   = Department::where('code', 'HR')->where('tenant_id', $tenantId)->first();
        $rcDept   = Department::where('code', 'RC')->where('tenant_id', $tenantId)->first();
        $itDept   = Department::where('code', 'IT')->where('tenant_id', $tenantId)->first();
        $csDept   = Department::where('code', 'CS')->where('tenant_id', $tenantId)->first();

        $controlsChecklist   = Checklist::where('title', 'Internal Controls Assessment')->where('tenant_id', $tenantId)->first();
        $complianceChecklist = Checklist::where('title', 'Regulatory Compliance Review')->where('tenant_id', $tenantId)->first();
        $financialChecklist  = Checklist::where('title', 'Financial Statements Review')->where('tenant_id', $tenantId)->first();
        $itChecklist         = Checklist::where('title', 'IT Security Controls Audit')->where('tenant_id', $tenantId)->first();
        $hrChecklist         = Checklist::where('title', 'Human Resources Compliance Checklist')->where('tenant_id', $tenantId)->first();

        $critical = Priority::where('name', 'Critical')->where('tenant_id', $tenantId)->first();
        $high     = Priority::where('name', 'High')->where('tenant_id', $tenantId)->first();
        $medium   = Priority::where('name', 'Medium')->where('tenant_id', $tenantId)->first();
        $low      = Priority::where('name', 'Low')->where('tenant_id', $tenantId)->first();

        $audits = [
            [
                'title'        => 'Q1 Financial Controls Review',
                'description'  => 'Quarterly review of financial controls and reporting accuracy for the first quarter.',
                'status'       => 'completed',
                'audit_date'   => '2026-03-31',
                'auditor_name' => 'Sarah Mitchell',
                'company_id'   => $acme?->id,
                'department_id' => $finDept?->id,
                'checklist_id' => $controlsChecklist?->id,
                'priority_id'  => $high?->id,
            ],
            [
                'title'        => 'Annual Regulatory Compliance Assessment',
                'description'  => 'Annual assessment of compliance with all applicable regulatory requirements.',
                'status'       => 'in_progress',
                'audit_date'   => '2026-06-15',
                'auditor_name' => 'James Okonkwo',
                'company_id'   => $pinnacle?->id,
                'department_id' => $rcDept?->id,
                'checklist_id' => $complianceChecklist?->id,
                'priority_id'  => $critical?->id,
            ],
            [
                'title'        => 'IT Security Posture Evaluation',
                'description'  => 'Comprehensive evaluation of IT security controls, vulnerability management, and incident response.',
                'status'       => 'planned',
                'audit_date'   => '2026-07-20',
                'auditor_name' => 'Priya Sharma',
                'company_id'   => $nextgen?->id,
                'department_id' => $itDept?->id,
                'checklist_id' => $itChecklist?->id,
                'priority_id'  => $high?->id,
            ],
            [
                'title'        => 'HR Policy Compliance Audit',
                'description'  => 'Review of human resources policies and procedures for compliance with employment legislation.',
                'status'       => 'planned',
                'audit_date'   => '2026-08-10',
                'auditor_name' => 'Linda Osei',
                'company_id'   => $acme?->id,
                'department_id' => $hrDept?->id,
                'checklist_id' => $hrChecklist?->id,
                'priority_id'  => $medium?->id,
            ],
            [
                'title'        => 'Clinical Services Quality Audit',
                'description'  => 'Quality assurance audit of clinical service delivery processes and patient safety standards.',
                'status'       => 'planned',
                'audit_date'   => '2026-09-05',
                'auditor_name' => 'Dr. Andrew Nkosi',
                'company_id'   => $horizon?->id,
                'department_id' => $csDept?->id,
                'checklist_id' => $complianceChecklist?->id,
                'priority_id'  => $critical?->id,
            ],
            [
                'title'        => 'Financial Statements Year-End Review',
                'description'  => 'Year-end review of financial statements and supporting documentation for accuracy and completeness.',
                'status'       => 'planned',
                'audit_date'   => '2026-12-15',
                'auditor_name' => 'Michael Tran',
                'company_id'   => $greenleaf?->id,
                'department_id' => null,
                'checklist_id' => $financialChecklist?->id,
                'priority_id'  => $low?->id,
            ],
        ];

        foreach ($audits as $data) {
            Audit::updateOrCreate(
                ['title' => $data['title'], 'tenant_id' => $tenantId],
                [
                    'description'   => $data['description'],
                    'status'        => $data['status'],
                    'audit_date'    => $data['audit_date'],
                    'auditor_name'  => $data['auditor_name'],
                    'company_id'    => $data['company_id'],
                    'department_id' => $data['department_id'],
                    'checklist_id'  => $data['checklist_id'],
                    'priority_id'   => $data['priority_id'],
                    'is_active'     => true,
                ]
            );
        }
    }
}
