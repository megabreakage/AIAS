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
        $tenantDomain = tenant()?->domain ?? tenant()?->domains?->first()?->domain ?? 'tenant.localhost';
        $email = (string) env('TEST_TENANT_ADMIN_EMAIL', "admin@{$tenantDomain}");
        $password = (string) env('TEST_TENANT_ADMIN_PASSWORD', 'password');
        $firstName = 'Tenant';
        $lastName = 'Owner';

        $admin = User::withoutEvents(function () use ($email, $password, $firstName, $lastName): User {
            return User::firstOrCreate(
                ['email' => $email],
                [
                    'identifier' => (string) Str::uuid(),
                    'title' => null,
                    'first_name' => $firstName,
                    'middle_name' => null,
                    'last_name' => $lastName,
                    'username' => 'tenant_owner_'.Str::random(4),
                    'email_verified_at' => now(),
                    'country_code' => '+254',
                    'phone' => null,
                    'password' => Hash::make($password),
                    'preferred_timezone' => 'Africa/Nairobi',
                    'office_location' => null,
                    'is_active' => true,
                    'avatar' => null,
                    'notes' => null,
                ],
            );
        });

        $tenantAdminRole = Role::where('name', 'tenant-admin')
            ->where('guard_name', 'api')
            ->first();

        if (!$tenantAdminRole) {
            $this->command->error('tenant-admin role not found. Run DefaultTenantRoleSeeder first.');

            return;
        }

        if (!$admin->hasRole('tenant-admin', 'api')) {
            $admin->assignRole($tenantAdminRole);
        }

        if ($admin->wasRecentlyCreated) {
            $this->command->info("Created default tenant admin: {$email}");
        } else {
            $this->command->line("Default tenant admin already exists: {$email}");
        }
    }
}
