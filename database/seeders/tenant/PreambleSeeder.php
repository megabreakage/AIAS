<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Enums\PreambleStatus;
use App\Models\Tenant\Preamble;
use Illuminate\Database\Seeder;

class PreambleSeeder extends Seeder
{
    public function run(): void
    {
        $preambles = [
            [
                'name' => 'Quality Management System',
                'description' => 'Establishes the framework for quality management processes and continuous improvement across the organization.',
                'status' => PreambleStatus::Active,
                'effective_date' => now()->subMonths(6),
                'is_featured' => true,
            ],
            [
                'name' => 'Environmental Compliance',
                'description' => 'Outlines environmental regulations, sustainability practices, and compliance requirements.',
                'status' => PreambleStatus::Active,
                'effective_date' => now()->subMonths(3),
                'is_featured' => false,
            ],
            [
                'name' => 'Occupational Health & Safety',
                'description' => 'Defines workplace safety standards, hazard identification, and risk mitigation protocols.',
                'status' => PreambleStatus::Draft,
                'effective_date' => null,
                'is_featured' => false,
            ],
            [
                'name' => 'Information Security',
                'description' => 'Covers data protection policies, access controls, and cybersecurity best practices.',
                'status' => PreambleStatus::Active,
                'effective_date' => now()->subMonth(),
                'is_featured' => true,
            ],
            [
                'name' => 'Financial Controls',
                'description' => 'Addresses internal financial controls, segregation of duties, and reporting standards.',
                'status' => PreambleStatus::Archived,
                'effective_date' => now()->subYear(),
                'is_featured' => false,
            ],
        ];

        foreach ($preambles as $data) {
            Preamble::firstOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}
