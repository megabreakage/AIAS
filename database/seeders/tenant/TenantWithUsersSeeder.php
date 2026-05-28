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

        $this->command->info('Creating 2 tenants with users...\\n');

        $tenantRole = Role::where('name', 'tenant')->where('guard_name', 'api')->firstOrFail();
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->firstOrFail();

        Tenant::factory(2)->create()->each(function (Tenant $tenant) use ($tenantRole, $adminRole): void {
            $tenant->refresh();

            $tenantUser = User::firstOrCreate(
                ['email' => env('TEST_TENANT_ADMIN_EMAIL', 'admin@tenant.test')],
                [
                    'identifier' => (string) Str::uuid(),
                    'first_name' => 'Admin',
                    'last_name' => 'User',
                    'username' => Str::slug('admin-'.Str::random(4)),
                    'password' => Hash::make(env('TEST_TENANT_ADMIN_PASSWORD', 'password')),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'country_code' => '+254',
                ],
            );

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
