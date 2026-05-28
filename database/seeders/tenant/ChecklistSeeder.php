<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Checklist;
use App\Models\Tenant\ChecklistType;
use App\Models\Tenant\Preamble;
use Illuminate\Database\Seeder;

class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $complianceType = ChecklistType::where('name', 'Compliance Audit')->first();
        $processType = ChecklistType::where('name', 'Process Audit')->first();
        $safetyType = ChecklistType::where('name', 'Safety Inspection')->first();
        $qualityType = ChecklistType::where('name', 'Quality Control')->first();
        $financialType = ChecklistType::where('name', 'Financial Review')->first();

        $qmsPreamble = Preamble::where('name', 'Quality Management System')->first();
        $securityPreamble = Preamble::where('name', 'Information Security')->first();
        $financialPreamble = Preamble::where('name', 'Financial Controls')->first();
        $safetyPreamble = Preamble::where('name', 'Occupational Health & Safety')->first();

        $checklists = [
            [
                'name' => 'ISO 9001 Compliance Checklist',
                'preamble_id' => $qmsPreamble?->id,
                'checklist_type_id' => $complianceType?->id,
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Workplace Safety Checklist',
                'preamble_id' => $safetyPreamble?->id,
                'checklist_type_id' => $safetyType?->id,
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Financial Controls Checklist',
                'preamble_id' => $financialPreamble?->id,
                'checklist_type_id' => $financialType?->id,
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Data Protection Checklist',
                'preamble_id' => $securityPreamble?->id,
                'checklist_type_id' => $complianceType?->id,
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Supplier Evaluation Checklist',
                'preamble_id' => $qmsPreamble?->id,
                'checklist_type_id' => $processType?->id,
                'is_active' => true,
                'is_featured' => false,
            ],
        ];

        foreach ($checklists as $data) {
            Checklist::firstOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}
