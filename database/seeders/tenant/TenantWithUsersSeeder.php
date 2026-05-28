<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class TenantWithUsersSeeder extends Seeder
{
    public function run(): void
    {
        $existingCount = Tenant::count();

        if ($existingCount >= 2) {
            $this->command->warn("TenantWithUsersSeeder: {$existingCount} tenant(s) already exist. Skipping.");
            $this->command->info('To reseed from a clean state, run: php artisan migrate:fresh --seed');

            return;
        }

        $this->command->info('Creating 2 tenants with users...');

        $tenantRole = Role::where('name', 'tenant')->where('guard_name', 'api')->firstOrFail();
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->firstOrFail();

        Tenant::factory(2)->create()->each(function (Tenant $tenant) use ($tenantRole, $adminRole): void {
            $tenant->refresh();

            $users = User::factory(2)->create();

            $owner = $users->first();
            $owner->assignRole($tenantRole);
            $tenant->update(['owner_id' => $owner->id]);

            $users->last()->assignRole($adminRole);

            $this->command->info("Created tenant {$tenant->getTenantKey()} with 2 users.");

            $tenant->run(function () {
                $seeder = new TenantDatabaseSeeder;
                $seeder->setCommand($this->command);
                $seeder->run();
            });

            $this->command->info("Seeded tenant database for {$tenant->getTenantKey()}.\n");
        });
    }
}
