<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Central\CentralRolePermissionsSeeder;
use Database\Seeders\Central\ContinentSeeder;
use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\SuperAdminSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PassportClientSeeder::class,
            CentralRolePermissionsSeeder::class,
            SuperAdminSeeder::class,
            ContinentSeeder::class,
            CountrySeeder::class,
        ]);
    }
}
