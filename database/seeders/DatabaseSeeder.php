<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Tenant\TenantDatabaseSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PassportClientSeeder::class,
            RolePermissionsSeeder::class,
            SuperAdminSeeder::class,
            ContinentSeeder::class,
            CountrySeeder::class,
            TenantDatabaseSeeder::class,
        ]);
    }
}
