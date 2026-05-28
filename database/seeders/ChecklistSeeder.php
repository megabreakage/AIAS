<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Checklist;
use App\Models\ChecklistType;
use App\Models\Preamble;
use Illuminate\Database\Seeder;

final class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant()?->id;

        $internalAudit  = ChecklistType::where('code', 'IA')->where('tenant_id', $tenantId)->first();
        $complianceAudit = ChecklistType::where('code', 'CA')->where('tenant_id', $tenantId)->first();
        $operationalAudit = ChecklistType::where('code', 'OA')->where('tenant_id', $tenantId)->first();
        $financialAudit  = ChecklistType::where('code', 'FA')->where('tenant_id', $tenantId)->first();
        $itAudit         = ChecklistType::where('code', 'ITA')->where('tenant_id', $tenantId)->first();

        $execPreamble    = Preamble::where('type', 'executive')->where('tenant_id', $tenantId)->first();
        $scopePreamble   = Preamble::where('type', 'scope')->where('tenant_id', $tenantId)->first();
        $riskPreamble    = Preamble::where('type', 'risk')->where('tenant_id', $tenantId)->first();

        $checklists = [
            [
                'title'             => 'Internal Controls Assessment',
                'description'       => 'Comprehensive checklist for evaluating the design and effectiveness of internal controls across key business processes.',
                'version'           => '1.0',
                'checklist_type_id' => $internalAudit?->id,
                'preamble_id'       => $execPreamble?->id,
            ],
            [
                'title'             => 'Regulatory Compliance Review',
                'description'       => 'Checklist to verify adherence to applicable laws, regulations, and industry standards.',
                'version'           => '2.1',
                'checklist_type_id' => $complianceAudit?->id,
                'preamble_id'       => $scopePreamble?->id,
            ],
            [
                'title'             => 'Procurement Process Audit',
                'description'       => 'Checklist reviewing procurement activities including vendor selection, purchase orders, and contract management.',
                'version'           => '1.0',
                'checklist_type_id' => $operationalAudit?->id,
                'preamble_id'       => null,
            ],
            [
                'title'             => 'Financial Statements Review',
                'description'       => 'Checklist for verifying the accuracy and completeness of financial statements and supporting schedules.',
                'version'           => '3.0',
                'checklist_type_id' => $financialAudit?->id,
                'preamble_id'       => $execPreamble?->id,
            ],
            [
                'title'             => 'IT Security Controls Audit',
                'description'       => 'Checklist assessing IT security controls, access management, data protection, and incident response procedures.',
                'version'           => '1.5',
                'checklist_type_id' => $itAudit?->id,
                'preamble_id'       => $riskPreamble?->id,
            ],
            [
                'title'             => 'Human Resources Compliance Checklist',
                'description'       => 'Checklist for evaluating HR processes including recruitment, onboarding, performance management, and employment law compliance.',
                'version'           => '1.0',
                'checklist_type_id' => $complianceAudit?->id,
                'preamble_id'       => null,
            ],
        ];

        foreach ($checklists as $data) {
            Checklist::updateOrCreate(
                ['title' => $data['title'], 'tenant_id' => $tenantId],
                [
                    'description'       => $data['description'],
                    'version'           => $data['version'],
                    'checklist_type_id' => $data['checklist_type_id'],
                    'preamble_id'       => $data['preamble_id'],
                    'is_active'         => true,
                ]
            );
        }
    }
}
