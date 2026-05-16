<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DefaultTenantAdminSeeder extends Seeder
{
    public function run(): void
    {
        $tenantDomain = tenant()?->domains?->first()?->domain ?? 'tenant.localhost';
        $email        = "admin@{$tenantDomain}";
        $password     = (string) env('TEST_TENANT_ADMIN_PASSWORD', 'password');

        $admin = User::withoutEvents(function () use ($email, $password): User {
            return User::firstOrCreate(
                ['email' => $email],
                [
                    'identifier'        => (string) Str::uuid(),
                    'first_name'        => 'Tenant',
                    'last_name'         => 'Admin',
                    'username'          => 'tenant_admin_' . Str::random(4),
                    'email_verified_at' => now(),
                    'password'          => Hash::make($password),
                    'is_active'         => true,
                    'country_code'      => '+254',
                ],
            );
        });

        if (! $admin->hasRole('tenant-admin', 'api')) {
            $admin->assignRole(Role::findByName('tenant-admin', 'api'));
        }

        if ($admin->wasRecentlyCreated) {
            $this->command->info("Created default tenant admin: {$email}");
        } else {
            $this->command->line("Default tenant admin already exists: {$email}");
        }
    }
}
