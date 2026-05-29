<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantRolePermissionsSeeder::class,
            PreambleSeeder::class,
            ChecklistTypeSeeder::class,
            SectionStyleSeeder::class,
            ChecklistSeeder::class,
            ChecklistSectionStyle::class,
            CompanySeeder::class,
            DepartmentSeeder::class,
            PriorityLevelSeeder::class,
            AuditSeeder::class,
        ]);
    }
}
