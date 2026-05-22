<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantRolePermissionsSeeder::class,
            DefaultTenantAdminSeeder::class,
        ]);
    }
}
