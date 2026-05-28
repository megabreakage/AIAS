<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use Illuminate\Database\Seeder;

final class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant()?->id;

        $acme      = Company::where('code', 'ACME')->where('tenant_id', $tenantId)->first();
        $pinnacle  = Company::where('code', 'PFS')->where('tenant_id', $tenantId)->first();
        $greenleaf = Company::where('code', 'GLE')->where('tenant_id', $tenantId)->first();
        $nextgen   = Company::where('code', 'NGTS')->where('tenant_id', $tenantId)->first();
        $horizon   = Company::where('code', 'HHG')->where('tenant_id', $tenantId)->first();

        $departments = [
            [
                'name'        => 'Finance',
                'code'        => 'FIN',
                'description' => 'Responsible for financial planning, reporting, and management of company funds.',
                'company_id'  => $acme?->id,
            ],
            [
                'name'        => 'Human Resources',
                'code'        => 'HR',
                'description' => 'Manages employee relations, recruitment, training, and compliance with labour laws.',
                'company_id'  => $acme?->id,
            ],
            [
                'name'        => 'Risk and Compliance',
                'code'        => 'RC',
                'description' => 'Oversees enterprise risk management and ensures regulatory compliance.',
                'company_id'  => $pinnacle?->id,
            ],
            [
                'name'        => 'Information Technology',
                'code'        => 'IT',
                'description' => 'Manages IT infrastructure, cybersecurity, and digital transformation initiatives.',
                'company_id'  => $nextgen?->id,
            ],
            [
                'name'        => 'Operations',
                'code'        => 'OPS',
                'description' => 'Responsible for day-to-day operational activities and process optimisation.',
                'company_id'  => $greenleaf?->id,
            ],
            [
                'name'        => 'Clinical Services',
                'code'        => 'CS',
                'description' => 'Provides clinical care services and manages healthcare quality standards.',
                'company_id'  => $horizon?->id,
            ],
        ];

        foreach ($departments as $data) {
            Department::updateOrCreate(
                ['name' => $data['name'], 'company_id' => $data['company_id'], 'tenant_id' => $tenantId],
                [
                    'code'        => $data['code'],
                    'description' => $data['description'],
                    'is_active'   => true,
                ]
            );
        }
    }
}
