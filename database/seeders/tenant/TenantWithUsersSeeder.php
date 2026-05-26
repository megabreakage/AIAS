<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

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

        Tenant::factory(2)->create()->each(function (Tenant $tenant): void {
            $tenant->refresh();

            dispatch_sync(new CreateDatabase($tenant));
            dispatch_sync(new MigrateDatabase($tenant));

            $users = User::factory(2)->create();

            $owner = $users->first();
            $owner->assignRole('tenant');
            $tenant->update(['owner_id' => $owner->id]);

            $users->last()->assignRole('admin');

            $this->command->info("Created tenant {$tenant->getTenantKey()} with 2 users.");
        });
    }
}
