<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DefaultTenantRoleSeeder::class,
            DefaultTenantAdminSeeder::class,
            PreambleSeeder::class,
            ChecklistTypeSeeder::class,
            SectionStyleSeeder::class,
            CompanySeeder::class,
            PrioritySeeder::class,
            ChecklistSeeder::class,
            DepartmentSeeder::class,
            ChecklistSectionStyleSeeder::class,
            AuditSeeder::class,
        ]);
    }
}
