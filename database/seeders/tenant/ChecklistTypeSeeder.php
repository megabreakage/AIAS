<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Tenant\ChecklistType;
use Illuminate\Database\Seeder;

class ChecklistTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Compliance Audit',
                'description' => 'Checklists for regulatory and standards compliance verification.',
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Process Audit',
                'description' => 'Checklists for evaluating operational processes and workflows.',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Safety Inspection',
                'description' => 'Checklists for workplace safety and hazard assessments.',
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Quality Control',
                'description' => 'Checklists for product and service quality verification.',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Financial Review',
                'description' => 'Checklists for financial audits and internal controls assessment.',
                'is_active' => true,
                'is_featured' => false,
            ],
        ];

        foreach ($types as $data) {
            ChecklistType::firstOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}
