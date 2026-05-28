<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ChecklistType;
use Illuminate\Database\Seeder;

final class ChecklistTypeSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant()?->id;

        $types = [
            [
                'name'        => 'Internal Audit',
                'code'        => 'IA',
                'description' => 'Checklist for internal audit engagements assessing internal controls and processes.',
            ],
            [
                'name'        => 'Compliance Audit',
                'code'        => 'CA',
                'description' => 'Checklist for evaluating compliance with regulatory requirements and internal policies.',
            ],
            [
                'name'        => 'Operational Audit',
                'code'        => 'OA',
                'description' => 'Checklist for reviewing the efficiency and effectiveness of operational processes.',
            ],
            [
                'name'        => 'Financial Audit',
                'code'        => 'FA',
                'description' => 'Checklist for examining financial statements and accounting records for accuracy.',
            ],
            [
                'name'        => 'IT Audit',
                'code'        => 'ITA',
                'description' => 'Checklist for assessing IT systems, cybersecurity controls, and data governance.',
            ],
            [
                'name'        => 'Environmental Audit',
                'code'        => 'EA',
                'description' => 'Checklist for evaluating adherence to environmental standards and sustainability practices.',
            ],
        ];

        foreach ($types as $data) {
            ChecklistType::updateOrCreate(
                ['name' => $data['name'], 'tenant_id' => $tenantId],
                [
                    'code'        => $data['code'],
                    'description' => $data['description'],
                    'is_active'   => true,
                ]
            );
        }
    }
}
