<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

        $this->command->info('Creating 2 tenants with users.');
        $this->command->info('...');

        $tenantRole = Role::where('name', 'tenant')->where('guard_name', 'api')->firstOrFail();
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->firstOrFail();
        $auditorRole = Role::where('name', 'auditor')->where('guard_name', 'api')->firstOrFail();

        $isFirstTenant = true;

        Tenant::factory(2)->create()->each(function (Tenant $tenant) use ($tenantRole, $adminRole, $auditorRole, &$isFirstTenant): void {
            $tenant->refresh();

            $tenantKey = $tenant->getTenantKey();

            // Update the auto-created owner's tenant_id
            if ($tenant->owner_id) {
                User::where('id', $tenant->owner_id)->update(['tenant_id' => $tenantKey]);
            }

            $users = User::factory(2)->create(['tenant_id' => $tenantKey]);

            $owner = $users->first();
            $owner->assignRole($tenantRole);
            $tenant->update(['owner_id' => $owner->id]);

            $users->last()->assignRole($auditorRole);

            $adminEmail = $isFirstTenant
                ? env('TEST_TENANT_ADMIN_EMAIL', 'admin@tenant.test')
                : 'admin-'.Str::random(4).'@tenant.test';

            User::firstOrCreate(
                ['email' => $adminEmail],
                [
                    'identifier' => (string) Str::uuid(),
                    'tenant_id' => $tenantKey,
                    'first_name' => 'Admin',
                    'last_name' => 'User',
                    'username' => Str::slug('admin-'.Str::random(4)),
                    'password' => Hash::make(env('TEST_TENANT_ADMIN_PASSWORD', 'password')),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'country_code' => '+254',
                ],
            )->assignRole($adminRole);

            $isFirstTenant = false;

            $this->command->info("Created tenant {$tenant->getTenantKey()} with 3 users.");

            $tenant->run(function () {
                $seeder = new TenantDatabaseSeeder;
                $seeder->setCommand($this->command);
                $seeder->run();
            });

            $this->command->info("Seeded tenant database for {$tenant->getTenantKey()}.");
            $this->command->info(' ');
        });
    }
}
